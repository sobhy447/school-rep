using Atlas.BuildingBlocks.Domain.Repositories;

namespace Atlas.Modules.Identity.Domain.Users;

public interface IUserRepository : IRepository<User, long>
{
    Task<bool> ExistsByUsernameAsync(long tenantId, string username, CancellationToken cancellationToken = default);

    Task<bool> ExistsByEmailAsync(long tenantId, string email, CancellationToken cancellationToken = default);

    Task<IReadOnlyList<User>> ListByTenantAsync(long tenantId, CancellationToken cancellationToken = default);
}
