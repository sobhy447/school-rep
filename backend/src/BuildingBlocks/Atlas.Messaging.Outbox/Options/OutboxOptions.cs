namespace Atlas.Messaging.Outbox.Options;

public sealed class OutboxOptions
{
    public const string SectionName = "Outbox";

    private static readonly IReadOnlyList<TimeSpan> DefaultRetryBackoff =
    [
        TimeSpan.FromMinutes(1),
        TimeSpan.FromMinutes(5),
        TimeSpan.FromMinutes(15),
        TimeSpan.FromHours(1),
        TimeSpan.FromHours(4),
        TimeSpan.FromHours(24)
    ];

    public bool ProcessorEnabled { get; set; } = true;

    public TimeSpan PollingInterval { get; set; } = TimeSpan.FromSeconds(10);

    public int BatchSize { get; set; } = 100;

    public int MaxRetryCount { get; set; } = 6;

    public IReadOnlyList<TimeSpan> RetryBackoff { get; set; } = DefaultRetryBackoff;

    public int PublishedRetentionDays { get; set; } = 30;

    public int DeadLetterRetentionDays { get; set; } = 90;

    public TimeSpan GetRetryDelay(int retryCount)
    {
        if (RetryBackoff.Count == 0)
        {
            return TimeSpan.Zero;
        }

        int index = Math.Clamp(retryCount, 0, RetryBackoff.Count - 1);
        return RetryBackoff[index];
    }
}
