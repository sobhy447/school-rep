namespace Atlas.SharedKernel.Events;

public interface IDomainEvent
{
    Guid EventId { get; }

    DateTime OccurredOnUtc { get; }
}
