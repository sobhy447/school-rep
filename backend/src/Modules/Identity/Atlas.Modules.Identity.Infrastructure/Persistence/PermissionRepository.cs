using Atlas.Modules.Identity.Domain.Permissions;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Identity.Infrastructure.Persistence;

internal sealed class PermissionRepository(IdentityDbContext context)
    : RepositoryBase<Permission>(context), IPermissionRepository
{
    public Task<bool> ExistsByCodeAsync(string permissionCode, CancellationToken cancellationToken = default) =>
        Set.AnyAsync(permission => permission.PermissionCode == permissionCode, cancellationToken);

    public async Task<IReadOnlyList<Permission>> ListAllAsync(CancellationToken cancellationToken = default) =>
        await Set.OrderBy(permission => permission.Module)
            .ThenBy(permission => permission.PermissionCode)
            .ToListAsync(cancellationToken);
}
