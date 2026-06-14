namespace Atlas.Caching.Redis.Options;

public sealed class RedisOptions
{
    public const string SectionName = "Redis";

    public string ConnectionString { get; set; } = string.Empty;

    public string InstanceName { get; set; } = "atlas";

    public TimeSpan DefaultEntryExpiration { get; set; } = TimeSpan.FromMinutes(5);

    public TimeSpan DefaultLockExpiration { get; set; } = TimeSpan.FromSeconds(30);

    public TimeSpan DefaultLockTimeout { get; set; } = TimeSpan.FromSeconds(5);

    public TimeSpan LockRetryDelay { get; set; } = TimeSpan.FromMilliseconds(100);
}
