using System.Text.Json;
using Atlas.Caching.Redis.Abstractions;
using Atlas.Caching.Redis.Options;
using Microsoft.Extensions.Options;
using StackExchange.Redis;

namespace Atlas.Caching.Redis.Internal;

internal sealed class RedisCacheService(
    IConnectionMultiplexer connection,
    ICacheKeyBuilder keyBuilder,
    IOptions<RedisOptions> options) : ICacheService
{
    private static readonly JsonSerializerOptions SerializerOptions = new(JsonSerializerDefaults.Web);

    private readonly RedisOptions _options = options.Value;

    private IDatabase Database => connection.GetDatabase();

    public async Task<T?> GetAsync<T>(
        string key,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default)
    {
        cancellationToken.ThrowIfCancellationRequested();

        RedisValue value = await Database.StringGetAsync(keyBuilder.Build(key, scope));

        return value.IsNullOrEmpty
            ? default
            : JsonSerializer.Deserialize<T>(value.ToString(), SerializerOptions);
    }

    public async Task SetAsync<T>(
        string key,
        T value,
        TimeSpan? expiration = null,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default)
    {
        cancellationToken.ThrowIfCancellationRequested();

        string payload = JsonSerializer.Serialize(value, SerializerOptions);

        await Database.StringSetAsync(
            keyBuilder.Build(key, scope),
            payload,
            expiration ?? _options.DefaultEntryExpiration);
    }

    public async Task RemoveAsync(
        string key,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default)
    {
        cancellationToken.ThrowIfCancellationRequested();

        await Database.KeyDeleteAsync(keyBuilder.Build(key, scope));
    }

    public async Task<T> GetOrCreateAsync<T>(
        string key,
        Func<CancellationToken, Task<T>> factory,
        TimeSpan? expiration = null,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(factory);
        cancellationToken.ThrowIfCancellationRequested();

        string redisKey = keyBuilder.Build(key, scope);

        RedisValue existing = await Database.StringGetAsync(redisKey);
        if (!existing.IsNullOrEmpty)
        {
            return JsonSerializer.Deserialize<T>(existing.ToString(), SerializerOptions)!;
        }

        T created = await factory(cancellationToken);

        string payload = JsonSerializer.Serialize(created, SerializerOptions);
        await Database.StringSetAsync(redisKey, payload, expiration ?? _options.DefaultEntryExpiration);

        return created;
    }
}
