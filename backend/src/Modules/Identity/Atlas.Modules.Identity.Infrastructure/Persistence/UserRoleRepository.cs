using Atlas.Modules.Identity.Domain.UserRoles;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Identity.Infrastructure.Persistence;

internal sealed class UserRoleRepository(IdentityDbContext context)
    : RepositoryBase<UserRole>(context), IUserRoleRepository
{
    public Task<bool> ExistsAsync(
        long tenantId,
        long userId,
        long roleId,
        long? companyId,
        CancellationToken cancellationToken = default) =>
        companyId is null
            ? Set.AnyAsync(
                userRole => userRole.TenantId == tenantId &&
                            userRole.UserId == userId &&
                            userRole.RoleId == roleId &&
                            userRole.CompanyId == null,
                cancellationToken)
            : Set.AnyAsync(
                userRole => userRole.TenantId == tenantId &&
                            userRole.UserId == userId &&
                            userRole.RoleId == roleId &&
                            userRole.CompanyId == companyId,
                cancellationToken);

    public async Task<IReadOnlyList<UserRole>> ListByUserAsync(
        long tenantId,
        long userId,
        CancellationToken cancellationToken = default) =>
        await Set.Where(userRole => userRole.TenantId == tenantId && userRole.UserId == userId)
            .OrderBy(userRole => userRole.RoleId)
            .ToListAsync(cancellationToken);
}
