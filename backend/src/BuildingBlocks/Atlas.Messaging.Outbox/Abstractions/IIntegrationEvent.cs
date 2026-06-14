namespace Atlas.Messaging.Outbox.Abstractions;

public interface IIntegrationEvent
{
    Guid EventId { get; }

    DateTime OccurredOnUtc { get; }

    string EventType { get; }

    string EventVersion { get; }
}
