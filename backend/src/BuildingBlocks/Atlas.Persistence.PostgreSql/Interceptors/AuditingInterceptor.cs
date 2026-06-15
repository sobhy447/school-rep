using Atlas.SharedKernel.Abstractions;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.ChangeTracking;
using Microsoft.EntityFrameworkCore.Diagnostics;

namespace Atlas.Persistence.PostgreSql.Interceptors;

public sealed class AuditingInterceptor(TimeProvider timeProvider) : SaveChangesInterceptor
{
    public override InterceptionResult<int> SavingChanges(
        DbContextEventData eventData,
        InterceptionResult<int> result)
    {
        if (eventData.Context is not null)
        {
            Apply(eventData.Context, timeProvider.GetUtcNow().UtcDateTime);
        }

        return base.SavingChanges(eventData, result);
    }

    public override ValueTask<InterceptionResult<int>> SavingChangesAsync(
        DbContextEventData eventData,
        InterceptionResult<int> result,
        CancellationToken cancellationToken = default)
    {
        if (eventData.Context is not null)
        {
            Apply(eventData.Context, timeProvider.GetUtcNow().UtcDateTime);
        }

        return base.SavingChangesAsync(eventData, result, cancellationToken);
    }

    private static void Apply(DbContext context, DateTime utcNow)
    {
        foreach (EntityEntry<IAuditableEntity> entry in context.ChangeTracker.Entries<IAuditableEntity>())
        {
            switch (entry.State)
            {
                case EntityState.Added:
                    entry.Entity.SetCreated(utcNow);
                    break;
                case EntityState.Modified:
                    entry.Entity.SetModified(utcNow);
                    break;
            }
        }
    }
}
