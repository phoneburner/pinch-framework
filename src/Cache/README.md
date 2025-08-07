# Cache

The default cache interface is `\PhoneBurner\Pinch\Component\Cache\Cache`. This
interface is a simple wrapper around the PSR-16 Simple Cache interface with defined
parameter and return types. It also enforces a TTL be set on all cache items.
Note that using `Ttl::max()` is functionally equivalent to not setting a TTL at all,
and should be used carefully when setting items that are truly not expected to expire.

The `Cache` interface extends the `AppendOnlyCache` interface, which provides
method signatures for working with caches that are "append only" in nature. This
should primarily be used for either in-memory caches or filesystem-based caches.

The bottom type in the `Cache` hierarchy is the `NullCache` class.
