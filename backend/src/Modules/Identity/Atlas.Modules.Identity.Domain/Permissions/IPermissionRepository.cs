using Atlas.BuildingBlocks.Domain.Repositories;

namespace Atlas.Modules.Identity.Domain.Permissions;

public interface IPermissionRepository : IRepository<Permission, long>
{
    Task<bool> ExistsByCodeAsync(string permissionCode, CancellationToken cancellationToken = default);

    Task<IReadOnlyList<Permission>> ListAllAsync(CancellationToken cancellationToken = default);
}
