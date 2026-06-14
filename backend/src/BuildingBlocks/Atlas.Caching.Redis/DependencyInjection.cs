using Atlas.Caching.Redis.Abstractions;
using Atlas.Caching.Redis.Internal;
using Atlas.Caching.Redis.Options;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.Extensions.Options;
using StackExchange.Redis;

namespace Atlas.Caching.Redis;

public static class DependencyInjection
{
    public static IServiceCollection AddAtlasCaching(
        this IServiceCollection services,
        Action<RedisOptions> configure)
    {
        ArgumentNullException.ThrowIfNull(configure);

        services.AddOptions<RedisOptions>()
            .Configure(configure)
            .Validate(
                options => !string.IsNullOrWhiteSpace(options.ConnectionString),
                "Redis: ConnectionString must be configured.")
            .Validate(
                options => !string.IsNullOrWhiteSpace(options.InstanceName),
                "Redis: InstanceName must be configured.")
            .Validate(
                options => options.DefaultEntryExpiration > TimeSpan.Zero,
                "Redis: DefaultEntryExpiration must be greater than zero.")
            .Validate(
                options => options.DefaultLockExpiration > TimeSpan.Zero,
                "Redis: DefaultLockExpiration must be greater than zero.")
            .ValidateOnStart();

        services.TryAddSingleton<IConnectionMultiplexer>(provider =>
        {
            RedisOptions options = provider.GetRequiredService<IOptions<RedisOptions>>().Value;
            return ConnectionMultiplexer.Connect(options.ConnectionString);
        });

        services.TryAddSingleton<ICacheKeyBuilder, CacheKeyBuilder>();
        services.TryAddSingleton<ICacheService, RedisCacheService>();
        services.TryAddSingleton<IDistributedLock, RedisDistributedLock>();

        return services;
    }
}
