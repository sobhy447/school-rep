using Atlas.Caching.Redis.Abstractions;
using Atlas.Caching.Redis.Options;
using Atlas.MultiTenancy.Abstractions;
using Microsoft.Extensions.Options;

namespace Atlas.Caching.Redis.Internal;

internal sealed class CacheKeyBuilder(ITenantContext tenantContext, IOptions<RedisOptions> options)
    : ICacheKeyBuilder
{
    private readonly RedisOptions _options = options.Value;

    public string Build(string key, CacheScope scope = CacheScope.Tenant)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(key);

        return scope == CacheScope.Global
            ? $"{_options.InstanceName}:g:{key}"
            : $"{_options.InstanceName}:t:{tenantContext.TenantId}:{key}";
    }
}
