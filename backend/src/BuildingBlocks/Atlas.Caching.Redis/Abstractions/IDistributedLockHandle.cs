namespace Atlas.Caching.Redis.Abstractions;

public interface IDistributedLockHandle : IAsyncDisposable
{
    string Resource { get; }
}
