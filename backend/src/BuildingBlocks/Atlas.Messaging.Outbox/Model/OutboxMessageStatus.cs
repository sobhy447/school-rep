namespace Atlas.Messaging.Outbox.Model;

public enum OutboxMessageStatus
{
    Pending = 0,
    Published = 1,
    Failed = 2,
    DeadLetter = 3
}
