using Atlas.BuildingBlocks.Domain.Repositories;

namespace Atlas.Modules.Identity.Domain.Roles;

public interface IRoleRepository : IRepository<Role, long>
{
    Task<bool> ExistsByCodeAsync(long? tenantId, string roleCode, CancellationToken cancellationToken = default);

    Task<IReadOnlyList<Role>> ListForTenantAsync(long tenantId, CancellationToken cancellationToken = default);
}
