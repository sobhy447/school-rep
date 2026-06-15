using Atlas.Modules.Tenancy.Domain.Tenants;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Tenancy.Infrastructure.Persistence;

internal sealed class TenantRepository(TenancyDbContext context)
    : RepositoryBase<Tenant>(context), ITenantRepository
{
    public Task<bool> ExistsByCodeAsync(string tenantCode, CancellationToken cancellationToken = default) =>
        Set.AnyAsync(tenant => tenant.TenantCode == tenantCode, cancellationToken);
}
