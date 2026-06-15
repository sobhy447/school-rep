using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.RolePermissions;

public sealed class RolePermission : AggregateRoot<long>, ITenantOwnedEntity
{
    private RolePermission()
    {
    }

    public long TenantId { get; private set; }

    public long RoleId { get; private set; }

    public long PermissionId { get; private set; }

    public DateTime CreatedAtUtc { get; private set; }

    public long CreatedBy { get; private set; }

    public static RolePermission Create(long tenantId, long roleId, long permissionId, long createdBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(tenantId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(roleId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(permissionId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(createdBy);

        return new RolePermission
        {
            TenantId = tenantId,
            RoleId = roleId,
            PermissionId = permissionId,
            CreatedBy = createdBy
        };
    }
}
