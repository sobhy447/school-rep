using Atlas.Modules.Identity.Domain.Roles;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Identity.Infrastructure.Persistence;

internal sealed class RoleRepository(IdentityDbContext context)
    : RepositoryBase<Role>(context), IRoleRepository
{
    public Task<bool> ExistsByCodeAsync(long? tenantId, string roleCode, CancellationToken cancellationToken = default) =>
        tenantId is null
            ? Set.AnyAsync(role => role.TenantId == null && role.RoleCode == roleCode, cancellationToken)
            : Set.AnyAsync(role => role.TenantId == tenantId && role.RoleCode == roleCode, cancellationToken);

    public async Task<IReadOnlyList<Role>> ListForTenantAsync(long tenantId, CancellationToken cancellationToken = default) =>
        await Set.Where(role => role.TenantId == tenantId || role.TenantId == null)
            .OrderBy(role => role.RoleCode)
            .ToListAsync(cancellationToken);
}
