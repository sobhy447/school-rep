namespace Atlas.Messaging.Outbox.Idempotency;

public interface IIdempotencyStore
{
    Task<bool> HasBeenProcessedAsync(
        Guid messageId,
        string consumer,
        CancellationToken cancellationToken = default);

    Task MarkAsProcessedAsync(
        Guid messageId,
        string consumer,
        CancellationToken cancellationToken = default);
}
