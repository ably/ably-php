<?php
namespace tests;

use Ably\PubSub\Defaults;

/**
 * Invariants about how this repository is packaged.
 *
 * These are cheap and run on every PR because each one guards a failure that
 * is otherwise silent until it reaches a consumer: a package published under
 * the wrong name, a file left behind in the bare `Ably\` namespace that would
 * collide with the legacy `ably/ably-php` package in a mixed install, a
 * version site that has drifted from the changelog, or a class that the
 * generated autoloader cannot find.
 */
class PackagingTest extends \PHPUnit\Framework\TestCase {

    private static $rootDir;

    public static function setUpBeforeClass(): void {
        self::$rootDir = dirname( __DIR__ );
    }

    private static function composerJson() {
        return json_decode( file_get_contents( self::$rootDir.'/composer.json' ), true );
    }

    /**
     * Every .php file under src/, with its declared namespace.
     *
     * @return array<string, string> relative path => namespace
     */
    private static function sourceNamespaces() {
        $namespaces = [];
        $srcDir = self::$rootDir.'/src';

        $files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $srcDir ) );
        foreach ( $files as $file ) {
            if ( $file->isDir() || $file->getExtension() !== 'php' ) {
                continue;
            }

            $relative = substr( $file->getPathname(), strlen( $srcDir ) + 1 );
            $matched = preg_match( '/^\s*namespace\s+([^;]+);/m', file_get_contents( $file->getPathname() ), $m );
            $namespaces[$relative] = $matched ? trim( $m[1] ) : '';
        }

        return $namespaces;
    }

    public function testComposerPackageName() {
        $this->assertSame( 'ably/pubsub-server', self::composerJson()['name'],
            'This repository publishes the ably/pubsub-server package' );
    }

    public function testPsr4MapsOnlyThePubSubNamespace() {
        $psr4 = self::composerJson()['autoload']['psr-4'];

        $this->assertSame( [ 'Ably\\PubSub\\' => 'src/' ], $psr4,
            'The package must not claim the bare Ably\\ prefix, which the legacy '
            .'ably/ably-php package owns: in a side-by-side install the autoloader '
            .'would resolve Ably\\AblyRest to whichever prefix path is searched first' );
    }

    public function testNoSourceFileDeclaresANamespaceOutsidePubSub() {
        $offenders = [];
        foreach ( self::sourceNamespaces() as $relative => $namespace ) {
            if ( $namespace !== 'Ably\\PubSub' && strpos( $namespace, 'Ably\\PubSub\\' ) !== 0 ) {
                $offenders[$relative] = $namespace;
            }
        }

        $this->assertSame( [], $offenders,
            'Every file under src/ must declare Ably\\PubSub or a child of it; '
            .'a file missed by the namespace move would collide with the legacy package' );
    }

    public function testSourceTreeIsNotEmpty() {
        $this->assertNotEmpty( self::sourceNamespaces(),
            'Sanity check: the namespace scan actually found files to check' );
    }

    public function testLibVersionMatchesTheTopChangelogEntry() {
        $changelog = file_get_contents( self::$rootDir.'/CHANGELOG.md' );

        $this->assertSame( 1, preg_match( '/^## \[([^\]]+)\]/m', $changelog, $m ),
            'Expected a "## [version](...)" heading in CHANGELOG.md' );
        $this->assertSame( $m[1], Defaults::LIB_VERSION,
            'Defaults::LIB_VERSION is the only version site and must match the '
            .'top CHANGELOG.md entry; the release pre-flight checks both against the tag' );
    }

    /**
     * The PHP analogue of "the wheel contains the files it should": every class
     * declared under src/ resolves through Composer's generated autoloader.
     */
    public function testEveryClassInSrcIsAutoloadable() {
        $unloadable = [];

        foreach ( self::sourceNamespaces() as $relative => $namespace ) {
            $className = $namespace.'\\'.basename( $relative, '.php' );

            if ( !class_exists( $className ) && !interface_exists( $className ) && !trait_exists( $className ) ) {
                $unloadable[] = $className;
            }
        }

        $this->assertSame( [], $unloadable,
            'Every class under src/ must be loadable through the Composer autoloader' );
    }

    public function testTheDoorIsLoadableAndFinal() {
        $door = new \ReflectionClass( \Ably\PubSub\Server::class );

        $this->assertTrue( $door->isFinal(), 'The door must be final' );
        $this->assertFalse( $door->getConstructor()->isPublic(), 'The door must not be instantiable' );
        $this->assertSame( 'ably-pubsub-server', \Ably\PubSub\Server::SERVER_AGENT_IDENTIFIER );
        $this->assertStringEndsWith( '-server', \Ably\PubSub\Server::SERVER_AGENT_IDENTIFIER,
            'The -server suffix is what grants the MAU server exemption' );
    }
}
