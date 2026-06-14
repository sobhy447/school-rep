namespace Atlas.Caching.Redis.Abstractions;

public interface IDistributedLock
{
    Task<IDistributedLockHandle?> AcquireAsync(
        string resource,
        TimeSpan expiration,
        TimeSpan timeout = default,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default);
}
