using Atlas.BuildingBlocks.Domain.Repositories;

namespace Atlas.Modules.Identity.Domain.RolePermissions;

public interface IRolePermissionRepository : IRepository<RolePermission, long>
{
    Task<bool> ExistsAsync(long tenantId, long roleId, long permissionId, CancellationToken cancellationToken = default);

    Task<IReadOnlyList<RolePermission>> ListByRoleAsync(long tenantId, long roleId, CancellationToken cancellationToken = default);
}
