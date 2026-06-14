namespace Atlas.Messaging.Outbox.Abstractions;

public interface IOutboxProcessor
{
    Task<int> ProcessPendingAsync(CancellationToken cancellationToken = default);
}
