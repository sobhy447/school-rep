using Atlas.Modules.Identity.Domain.RolePermissions;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Identity.Infrastructure.Persistence;

internal sealed class RolePermissionRepository(IdentityDbContext context)
    : RepositoryBase<RolePermission>(context), IRolePermissionRepository
{
    public Task<bool> ExistsAsync(long tenantId, long roleId, long permissionId, CancellationToken cancellationToken = default) =>
        Set.AnyAsync(
            rolePermission => rolePermission.TenantId == tenantId &&
                              rolePermission.RoleId == roleId &&
                              rolePermission.PermissionId == permissionId,
            cancellationToken);

    public async Task<IReadOnlyList<RolePermission>> ListByRoleAsync(
        long tenantId,
        long roleId,
        CancellationToken cancellationToken = default) =>
        await Set.Where(rolePermission => rolePermission.TenantId == tenantId && rolePermission.RoleId == roleId)
            .OrderBy(rolePermission => rolePermission.PermissionId)
            .ToListAsync(cancellationToken);
}
