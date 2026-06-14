using Atlas.MultiTenancy.Abstractions;
using Atlas.Persistence.PostgreSql.Abstractions;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.ChangeTracking;
using Microsoft.EntityFrameworkCore.Diagnostics;

namespace Atlas.Persistence.PostgreSql.Interceptors;

public sealed class AuditingInterceptor(ITenantContext tenantContext, TimeProvider timeProvider)
    : SaveChangesInterceptor
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

    private void Apply(DbContext context, DateTime utcNow)
    {
        foreach (EntityEntry entry in context.ChangeTracker.Entries())
        {
            if (entry.State == EntityState.Added && entry.Entity is ITenantOwnedEntity tenantOwned)
            {
                tenantOwned.SetTenant(tenantContext.TenantId);
            }

            if (entry.Entity is not IAuditableEntity auditable)
            {
                continue;
            }

            switch (entry.State)
            {
                case EntityState.Added:
                    auditable.SetCreated(utcNow);
                    break;
                case EntityState.Modified:
                    auditable.SetModified(utcNow);
                    break;
            }
        }
    }
}
