using System.Text.Json;
using Atlas.Messaging.Outbox.Abstractions;
using Atlas.Messaging.Outbox.Model;
using Atlas.MultiTenancy.Abstractions;
using Atlas.Persistence.PostgreSql.Context;

namespace Atlas.Persistence.PostgreSql.Outbox;

public sealed class OutboxWriter(
    AtlasDbContextBase context,
    ITenantContext tenantContext,
    TimeProvider timeProvider) : IOutboxWriter
{
    private static readonly JsonSerializerOptions SerializerOptions = new(JsonSerializerDefaults.Web);

    public async Task WriteAsync(
        IIntegrationEvent integrationEvent,
        string aggregateType,
        long aggregateId,
        Guid? correlationId = null,
        Guid? causationId = null,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(integrationEvent);

        string payloadJson = JsonSerializer.Serialize(
            integrationEvent,
            integrationEvent.GetType(),
            SerializerOptions);

        OutboxMessage message = OutboxMessage.Create(
            tenantContext.TenantId,
            integrationEvent.EventType,
            integrationEvent.EventVersion,
            aggregateType,
            aggregateId,
            payloadJson,
            timeProvider.GetUtcNow().UtcDateTime,
            correlationId,
            causationId);

        await context.OutboxMessages.AddAsync(message, cancellationToken);
    }
}
