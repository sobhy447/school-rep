using Atlas.Messaging.Outbox.Options;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Options;

namespace Atlas.Messaging.Outbox;

public static class DependencyInjection
{
    public static IServiceCollection AddOutboxMessaging(
        this IServiceCollection services,
        Action<OutboxOptions>? configure = null)
    {
        OptionsBuilder<OutboxOptions> optionsBuilder = services.AddOptions<OutboxOptions>();

        if (configure is not null)
        {
            optionsBuilder.Configure(configure);
        }

        optionsBuilder
            .Validate(options => options.BatchSize > 0, "Outbox: BatchSize must be greater than zero.")
            .Validate(options => options.MaxRetryCount >= 0, "Outbox: MaxRetryCount cannot be negative.")
            .Validate(options => options.PollingInterval > TimeSpan.Zero, "Outbox: PollingInterval must be greater than zero.")
            .Validate(options => options.RetryBackoff.Count > 0, "Outbox: RetryBackoff must contain at least one interval.")
            .Validate(options => options.PublishedRetentionDays > 0, "Outbox: PublishedRetentionDays must be greater than zero.")
            .Validate(options => options.DeadLetterRetentionDays > 0, "Outbox: DeadLetterRetentionDays must be greater than zero.")
            .ValidateOnStart();

        return services;
    }
}
