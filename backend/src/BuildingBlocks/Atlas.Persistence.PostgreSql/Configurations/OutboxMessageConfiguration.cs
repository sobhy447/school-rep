using Atlas.Messaging.Outbox.Model;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Persistence.PostgreSql.Configurations;

public sealed class OutboxMessageConfiguration : IEntityTypeConfiguration<OutboxMessage>
{
    public void Configure(EntityTypeBuilder<OutboxMessage> builder)
    {
        builder.ToTable("event_outbox");

        builder.HasKey(message => message.Id);
        builder.Property(message => message.Id).ValueGeneratedOnAdd();

        builder.Property(message => message.TenantId).IsRequired();
        builder.Property(message => message.EventType).HasMaxLength(300).IsRequired();
        builder.Property(message => message.EventVersion).HasMaxLength(20).IsRequired();
        builder.Property(message => message.AggregateType).HasMaxLength(200).IsRequired();
        builder.Property(message => message.AggregateId).IsRequired();
        builder.Property(message => message.CorrelationId);
        builder.Property(message => message.CausationId);
        builder.Property(message => message.PayloadJson).HasColumnType("jsonb").IsRequired();
        builder.Property(message => message.CreatedOnUtc).IsRequired();
        builder.Property(message => message.PublishedOnUtc);
        builder.Property(message => message.Status)
            .HasConversion<string>()
            .HasMaxLength(50)
            .IsRequired();
        builder.Property(message => message.RetryCount).IsRequired();
        builder.Property(message => message.NextRetryOnUtc);
        builder.Property(message => message.LastError);
        builder.Property(message => message.DeadLetteredOnUtc);

        builder.HasIndex(message => new { message.TenantId, message.Status, message.NextRetryOnUtc })
            .HasDatabaseName("ix_outbox_pending");
        builder.HasIndex(message => new { message.TenantId, message.AggregateType, message.AggregateId })
            .HasDatabaseName("ix_outbox_aggregate");
        builder.HasIndex(message => message.CorrelationId)
            .HasDatabaseName("ix_outbox_correlation");
        builder.HasIndex(message => new { message.TenantId, message.CreatedOnUtc })
            .HasDatabaseName("ix_outbox_created");
    }
}
