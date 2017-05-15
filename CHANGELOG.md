# Change Log

## [1.0.0](https://github.com/ably/ably-php/tree/1.0.0)

[Full Changelog](https://github.com/ably/ably-php/compare/0.9.0...1.0.0)

### v1.0 release and upgrade notes from v0.8

- See https://github.com/ably/docs/issues/235

**Implemented enhancements:**

- PaginatedResult hasNext and isLast are attributes [\#41](https://github.com/ably/ably-php/issues/41)

## [0.9.0](https://github.com/ably/ably-php/tree/0.9.0) (2016-08-12)
[Full Changelog](https://github.com/ably/ably-php/compare/0.8.4...0.9.0)

**Fixed bugs:**

- cURL error: SSL certificate problem: self signed certificate in certificate chain  [\#32](https://github.com/ably/ably-php/issues/32)

**Closed issues:**

- connectionKey attribute for messages [\#35](https://github.com/ably/ably-php/issues/35)
- Add Laravel support [\#33](https://github.com/ably/ably-php/issues/33)

**Merged pull requests:**

- 0.9 compatibility - version HTTP headers, fallbackHosts, request\(\) and decoding interop. test [\#37](https://github.com/ably/ably-php/pull/37) ([bladeSk](https://github.com/bladeSk))
- Added connectionKey to Message, updated README [\#36](https://github.com/ably/ably-php/pull/36) ([bladeSk](https://github.com/bladeSk))

## [0.8.4](https://github.com/ably/ably-php/tree/0.8.4) (2016-03-23)
[Full Changelog](https://github.com/ably/ably-php/compare/0.8.3...0.8.4)

**Implemented enhancements:**

- Review PHP docs [\#30](https://github.com/ably/ably-php/issues/30)
- New Crypto Spec [\#29](https://github.com/ably/ably-php/issues/29)

**Fixed bugs:**

- Mac mismatch - possible timezone issue? [\#26](https://github.com/ably/ably-php/issues/26)
- Spec references [\#19](https://github.com/ably/ably-php/issues/19)

**Merged pull requests:**

- Updated Crypto and CipherParams [\#31](https://github.com/ably/ably-php/pull/31) ([bladeSk](https://github.com/bladeSk))

## [0.8.3](https://github.com/ably/ably-php/tree/0.8.3) (2016-02-17)
[Full Changelog](https://github.com/ably/ably-php/compare/0.8.2...0.8.3)

**Fixed bugs:**

- Token reissue bug fix [\#25](https://github.com/ably/ably-php/issues/25)

**Merged pull requests:**

- Fixed bug with createTokenRequest not working on some PHP configs [\#28](https://github.com/ably/ably-php/pull/28) ([bladeSk](https://github.com/bladeSk))

## [0.8.2](https://github.com/ably/ably-php/tree/0.8.2) (2016-01-14)
[Full Changelog](https://github.com/ably/ably-php/compare/0.8.1...0.8.2)

**Implemented enhancements:**

- Travis version support [\#21](https://github.com/ably/ably-php/issues/21)
- 0.8.x finalisation [\#20](https://github.com/ably/ably-php/issues/20)
- Switch arity of auth methods [\#16](https://github.com/ably/ably-php/issues/16)

**Fixed bugs:**

- Do not persist authorise attributes force & timestamp  [\#23](https://github.com/ably/ably-php/issues/23)
- Travis version support [\#21](https://github.com/ably/ably-php/issues/21)
- Switch arity of auth methods [\#16](https://github.com/ably/ably-php/issues/16)

**Merged pull requests:**

- Moved `force` parameter out of authorise\(\) to AuthOptions [\#24](https://github.com/ably/ably-php/pull/24) ([bladeSk](https://github.com/bladeSk))
- Fixed clientId handling [\#22](https://github.com/ably/ably-php/pull/22) ([bladeSk](https://github.com/bladeSk))

## [0.8.1](https://github.com/ably/ably-php/tree/0.8.1) (2015-12-15)
[Full Changelog](https://github.com/ably/ably-php/compare/0.8.0...0.8.1)

**Implemented enhancements:**

- Bring inline with latest 0.8.\* spec [\#15](https://github.com/ably/ably-php/issues/15)
- Spec validation [\#10](https://github.com/ably/ably-php/issues/10)
- Consistent README [\#8](https://github.com/ably/ably-php/issues/8)
- API changes Apr 2015 [\#7](https://github.com/ably/ably-php/issues/7)

**Fixed bugs:**

- Presence test is failing [\#13](https://github.com/ably/ably-php/issues/13)
- API changes Apr 2015 [\#7](https://github.com/ably/ably-php/issues/7)

**Closed issues:**

- Support message encoding/decoding [\#1](https://github.com/ably/ably-php/issues/1)

**Merged pull requests:**

- V0.8 spec update [\#18](https://github.com/ably/ably-php/pull/18) ([bladeSk](https://github.com/bladeSk))
- Updates to exactly match the new 0.8 spec [\#17](https://github.com/ably/ably-php/pull/17) ([bladeSk](https://github.com/bladeSk))

## [0.8.0](https://github.com/ably/ably-php/tree/0.8.0) (2015-05-18)
**Implemented enhancements:**

- Rename to ably-php-rest [\#4](https://github.com/ably/ably-php/issues/4)

**Merged pull requests:**

- Demo, bug fixes, Heroku [\#12](https://github.com/ably/ably-php/pull/12) ([bladeSk](https://github.com/bladeSk))
- More tweaks to match the spec [\#11](https://github.com/ably/ably-php/pull/11) ([bladeSk](https://github.com/bladeSk))
- Complete functionality [\#9](https://github.com/ably/ably-php/pull/9) ([bladeSk](https://github.com/bladeSk))
- ably-common integration [\#6](https://github.com/ably/ably-php/pull/6) ([bladeSk](https://github.com/bladeSk))
- PresenceMessage decoding and tests + namespace/Composer rewrite [\#5](https://github.com/ably/ably-php/pull/5) ([bladeSk](https://github.com/bladeSk))
- Update readme and add travis [\#3](https://github.com/ably/ably-php/pull/3) ([kouno](https://github.com/kouno))
- Presence tests and channel pagination [\#2](https://github.com/ably/ably-php/pull/2) ([bladeSk](https://github.com/bladeSk))



\* *This Change Log was automatically generated by [github_changelog_generator](https://github.com/skywinder/Github-Changelog-Generator)*
