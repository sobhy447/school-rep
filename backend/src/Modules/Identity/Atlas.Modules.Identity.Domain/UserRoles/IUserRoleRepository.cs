using Atlas.BuildingBlocks.Domain.Repositories;

namespace Atlas.Modules.Identity.Domain.UserRoles;

public interface IUserRoleRepository : IRepository<UserRole, long>
{
    Task<bool> ExistsAsync(
        long tenantId,
        long userId,
        long roleId,
        long? companyId,
        CancellationToken cancellationToken = default);

    Task<IReadOnlyList<UserRole>> ListByUserAsync(long tenantId, long userId, CancellationToken cancellationToken = default);
}
