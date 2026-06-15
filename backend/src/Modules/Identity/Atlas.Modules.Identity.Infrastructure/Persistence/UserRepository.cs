using Atlas.Modules.Identity.Domain.Users;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Identity.Infrastructure.Persistence;

internal sealed class UserRepository(IdentityDbContext context)
    : RepositoryBase<User>(context), IUserRepository
{
    public Task<bool> ExistsByUsernameAsync(long tenantId, string username, CancellationToken cancellationToken = default) =>
        Set.AnyAsync(user => user.TenantId == tenantId && user.Username.Value == username, cancellationToken);

    public Task<bool> ExistsByEmailAsync(long tenantId, string email, CancellationToken cancellationToken = default) =>
        Set.AnyAsync(user => user.TenantId == tenantId && user.Email != null && user.Email.Value == email, cancellationToken);

    public async Task<IReadOnlyList<User>> ListByTenantAsync(long tenantId, CancellationToken cancellationToken = default) =>
        await Set.Where(user => user.TenantId == tenantId)
            .OrderBy(user => user.Username.Value)
            .ToListAsync(cancellationToken);
}
