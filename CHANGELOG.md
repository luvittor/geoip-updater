# Changelog

## v0.1.0

- Fix handling of the final HTTP response after MaxMind download redirects.
- Block HTTPS-to-HTTP redirect downgrades and remove `Authorization` on origin changes.
- Add structured, sanitized failure diagnostics to `UpdateException` and failed `UpdateResult` values: `errorCode`, `phase`, `httpStatus`, and `attempts`.
- Add coverage for redirect chains and limits, cross-origin credentials, HTTP failures, retries, connection failures, and diagnostic propagation.
- Streaming downloads remain intentionally out of scope and are a future improvement.
