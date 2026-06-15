using Atlas.BuildingBlocks.Domain.Repositories;

namespace Atlas.Modules.Tenancy.Domain.Tenants;

public interface ITenantRepository : IRepository<Tenant, long>
{
    Task<bool> ExistsByCodeAsync(string tenantCode, CancellationToken cancellationToken = default);
}
