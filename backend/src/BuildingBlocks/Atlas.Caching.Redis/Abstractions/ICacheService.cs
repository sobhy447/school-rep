namespace Atlas.Caching.Redis.Abstractions;

public interface ICacheService
{
    Task<T?> GetAsync<T>(
        string key,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default);

    Task SetAsync<T>(
        string key,
        T value,
        TimeSpan? expiration = null,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default);

    Task RemoveAsync(
        string key,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default);

    Task<T> GetOrCreateAsync<T>(
        string key,
        Func<CancellationToken, Task<T>> factory,
        TimeSpan? expiration = null,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default);
}
