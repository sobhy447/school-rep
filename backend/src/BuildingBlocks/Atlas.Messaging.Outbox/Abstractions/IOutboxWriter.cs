namespace Atlas.Messaging.Outbox.Abstractions;

public interface IOutboxWriter
{
    Task WriteAsync(
        IIntegrationEvent integrationEvent,
        string aggregateType,
        long aggregateId,
        Guid? correlationId = null,
        Guid? causationId = null,
        CancellationToken cancellationToken = default);
}
