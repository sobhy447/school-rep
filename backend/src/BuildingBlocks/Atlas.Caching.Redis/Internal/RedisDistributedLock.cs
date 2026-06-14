using System.Diagnostics;
using Atlas.Caching.Redis.Abstractions;
using Atlas.Caching.Redis.Options;
using Microsoft.Extensions.Options;
using StackExchange.Redis;

namespace Atlas.Caching.Redis.Internal;

internal sealed class RedisDistributedLock(
    IConnectionMultiplexer connection,
    ICacheKeyBuilder keyBuilder,
    IOptions<RedisOptions> options) : IDistributedLock
{
    private const string ReleaseScript =
        "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";

    private readonly RedisOptions _options = options.Value;

    public async Task<IDistributedLockHandle?> AcquireAsync(
        string resource,
        TimeSpan expiration,
        TimeSpan timeout = default,
        CacheScope scope = CacheScope.Tenant,
        CancellationToken cancellationToken = default)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(resource);

        IDatabase database = connection.GetDatabase();
        string key = keyBuilder.Build($"lock:{resource}", scope);
        string token = Guid.NewGuid().ToString("N");

        TimeSpan effectiveExpiration = expiration > TimeSpan.Zero ? expiration : _options.DefaultLockExpiration;
        TimeSpan effectiveTimeout = timeout > TimeSpan.Zero ? timeout : _options.DefaultLockTimeout;

        long startTimestamp = Stopwatch.GetTimestamp();

        while (true)
        {
            cancellationToken.ThrowIfCancellationRequested();

            if (await database.StringSetAsync(key, token, effectiveExpiration, When.NotExists))
            {
                return new RedisLockHandle(database, key, token, resource);
            }

            if (Stopwatch.GetElapsedTime(startTimestamp) >= effectiveTimeout)
            {
                return null;
            }

            await Task.Delay(_options.LockRetryDelay, cancellationToken);
        }
    }

    private sealed class RedisLockHandle(IDatabase database, string key, string token, string resource)
        : IDistributedLockHandle
    {
        private int _released;

        public string Resource => resource;

        public async ValueTask DisposeAsync()
        {
            if (Interlocked.Exchange(ref _released, 1) == 1)
            {
                return;
            }

            await database.ScriptEvaluateAsync(
                ReleaseScript,
                [(RedisKey)key],
                [(RedisValue)token]);
        }
    }
}
