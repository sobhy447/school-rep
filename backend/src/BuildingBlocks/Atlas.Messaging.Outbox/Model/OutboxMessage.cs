namespace Atlas.Messaging.Outbox.Model;

public sealed class OutboxMessage
{
    private OutboxMessage()
    {
        EventType = string.Empty;
        EventVersion = string.Empty;
        AggregateType = string.Empty;
        PayloadJson = string.Empty;
    }

    public long Id { get; private set; }

    public long TenantId { get; private set; }

    public string EventType { get; private set; }

    public string EventVersion { get; private set; }

    public string AggregateType { get; private set; }

    public long AggregateId { get; private set; }

    public Guid? CorrelationId { get; private set; }

    public Guid? CausationId { get; private set; }

    public string PayloadJson { get; private set; }

    public DateTime CreatedOnUtc { get; private set; }

    public DateTime? PublishedOnUtc { get; private set; }

    public OutboxMessageStatus Status { get; private set; }

    public int RetryCount { get; private set; }

    public DateTime? NextRetryOnUtc { get; private set; }

    public string? LastError { get; private set; }

    public DateTime? DeadLetteredOnUtc { get; private set; }

    public static OutboxMessage Create(
        long tenantId,
        string eventType,
        string eventVersion,
        string aggregateType,
        long aggregateId,
        string payloadJson,
        DateTime createdOnUtc,
        Guid? correlationId = null,
        Guid? causationId = null)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(eventType);
        ArgumentException.ThrowIfNullOrWhiteSpace(eventVersion);
        ArgumentException.ThrowIfNullOrWhiteSpace(aggregateType);
        ArgumentException.ThrowIfNullOrWhiteSpace(payloadJson);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(tenantId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(aggregateId);

        return new OutboxMessage
        {
            TenantId = tenantId,
            EventType = eventType,
            EventVersion = eventVersion,
            AggregateType = aggregateType,
            AggregateId = aggregateId,
            PayloadJson = payloadJson,
            CorrelationId = correlationId,
            CausationId = causationId,
            CreatedOnUtc = createdOnUtc,
            Status = OutboxMessageStatus.Pending,
            RetryCount = 0,
            NextRetryOnUtc = null
        };
    }

    public void MarkPublished(DateTime publishedOnUtc)
    {
        Status = OutboxMessageStatus.Published;
        PublishedOnUtc = publishedOnUtc;
        NextRetryOnUtc = null;
        LastError = null;
    }

    public void MarkFailed(string error, DateTime nextRetryOnUtc)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(error);

        Status = OutboxMessageStatus.Failed;
        RetryCount += 1;
        LastError = error;
        NextRetryOnUtc = nextRetryOnUtc;
    }

    public void MarkDeadLettered(string error, DateTime deadLetteredOnUtc)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(error);

        Status = OutboxMessageStatus.DeadLetter;
        RetryCount += 1;
        LastError = error;
        NextRetryOnUtc = null;
        DeadLetteredOnUtc = deadLetteredOnUtc;
    }
}
