using Atlas.Messaging.Outbox.Model;

namespace Atlas.Messaging.Outbox.Abstractions;

public interface IEventPublisher
{
    Task PublishAsync(OutboxMessage message, CancellationToken cancellationToken = default);
}
