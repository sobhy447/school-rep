using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.UserRoles;

public sealed class UserRole : AggregateRoot<long>, ITenantOwnedEntity
{
    private UserRole()
    {
    }

    public long TenantId { get; private set; }

    public long UserId { get; private set; }

    public long RoleId { get; private set; }

    public long? CompanyId { get; private set; }

    public DateTime AssignedAtUtc { get; private set; }

    public long AssignedBy { get; private set; }

    public DateTime? ExpiresAtUtc { get; private set; }

    public static UserRole Create(
        long tenantId,
        long userId,
        long roleId,
        long? companyId,
        long assignedBy,
        DateTime? expiresAtUtc)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(tenantId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(userId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(roleId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(assignedBy);

        if (companyId is <= 0)
        {
            throw new ArgumentOutOfRangeException(nameof(companyId), companyId, "CompanyId must be positive when provided.");
        }

        return new UserRole
        {
            TenantId = tenantId,
            UserId = userId,
            RoleId = roleId,
            CompanyId = companyId,
            AssignedBy = assignedBy,
            ExpiresAtUtc = expiresAtUtc
        };
    }
}
