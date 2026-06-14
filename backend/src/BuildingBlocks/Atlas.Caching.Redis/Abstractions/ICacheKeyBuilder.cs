namespace Atlas.Caching.Redis.Abstractions;

public interface ICacheKeyBuilder
{
    string Build(string key, CacheScope scope = CacheScope.Tenant);
}
