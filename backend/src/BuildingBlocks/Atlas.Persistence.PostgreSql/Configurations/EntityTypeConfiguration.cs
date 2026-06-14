using Atlas.Persistence.PostgreSql.Abstractions;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Persistence.PostgreSql.Configurations;

public abstract class EntityTypeConfiguration<TEntity> : IEntityTypeConfiguration<TEntity>
    where TEntity : class
{
    public void Configure(EntityTypeBuilder<TEntity> builder)
    {
        ApplyCrossCuttingColumns(builder);
        ConfigureEntity(builder);
    }

    protected abstract void ConfigureEntity(EntityTypeBuilder<TEntity> builder);

    private static void ApplyCrossCuttingColumns(EntityTypeBuilder<TEntity> builder)
    {
        if (typeof(ITenantOwnedEntity).IsAssignableFrom(typeof(TEntity)))
        {
            builder.Property(nameof(ITenantOwnedEntity.TenantId)).IsRequired();
        }

        if (typeof(IAuditableEntity).IsAssignableFrom(typeof(TEntity)))
        {
            builder.Property(nameof(IAuditableEntity.CreatedAtUtc)).IsRequired();
            builder.Property(nameof(IAuditableEntity.UpdatedAtUtc));
        }

        if (typeof(ISoftDeletableEntity).IsAssignableFrom(typeof(TEntity)))
        {
            builder.Property(nameof(ISoftDeletableEntity.IsDeleted)).IsRequired().HasDefaultValue(false);
            builder.Property(nameof(ISoftDeletableEntity.DeletedAtUtc));
        }

        if (typeof(IVersionedEntity).IsAssignableFrom(typeof(TEntity)))
        {
            builder.Property(nameof(IVersionedEntity.EntityVersion)).IsConcurrencyToken().IsRequired();
        }
    }
}
