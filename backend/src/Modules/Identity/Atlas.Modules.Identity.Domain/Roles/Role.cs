using Atlas.Modules.Identity.Domain.Roles.Events;
using Atlas.Modules.Identity.Domain.ValueObjects;
using Atlas.SharedKernel.Abstractions;
using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.Roles;

public sealed class Role : AggregateRoot<long>, IAuditableEntity, ISoftDeletableEntity
{
    public const int CodeMaxLength = 100;

    private Role()
    {
        RoleCode = null!;
        Name = null!;
    }

    public long? TenantId { get; private set; }

    public string RoleCode { get; private set; }

    public LocalizedText Name { get; private set; }

    public bool IsSystemRole { get; private set; }

    public bool IsActive { get; private set; }

    public bool IsDeleted { get; private set; }

    public DateTime? DeletedAtUtc { get; private set; }

    public long? DeletedBy { get; private set; }

    public DateTime CreatedAtUtc { get; private set; }

    public long CreatedBy { get; private set; }

    public DateTime? UpdatedAtUtc { get; private set; }

    public long? UpdatedBy { get; private set; }

    public static Role Create(long? tenantId, string roleCode, LocalizedText name, bool isSystemRole, long createdBy)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(roleCode);
        ArgumentNullException.ThrowIfNull(name);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(roleCode.Trim().Length, CodeMaxLength);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(createdBy);

        if (isSystemRole && tenantId is not null)
        {
            throw new ArgumentException("A system role cannot be scoped to a tenant.", nameof(isSystemRole));
        }

        if (!isSystemRole && tenantId is null)
        {
            throw new ArgumentException("A non-system role must be scoped to a tenant.", nameof(tenantId));
        }

        if (tenantId is <= 0)
        {
            throw new ArgumentOutOfRangeException(nameof(tenantId), tenantId, "TenantId must be positive when provided.");
        }

        var role = new Role
        {
            TenantId = tenantId,
            RoleCode = roleCode.Trim(),
            Name = name,
            IsSystemRole = isSystemRole,
            IsActive = true,
            IsDeleted = false,
            CreatedBy = createdBy
        };

        role.RaiseDomainEvent(new RoleCreatedDomainEvent(role.TenantId, role.RoleCode));
        return role;
    }

    public void Rename(LocalizedText name, long updatedBy)
    {
        ArgumentNullException.ThrowIfNull(name);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);

        Name = name;
        UpdatedBy = updatedBy;
    }

    public void Activate(long updatedBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);
        IsActive = true;
        UpdatedBy = updatedBy;
    }

    public void Deactivate(long updatedBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);
        IsActive = false;
        UpdatedBy = updatedBy;
    }

    public void MarkDeleted(DateTime timestampUtc)
    {
        IsDeleted = true;
        DeletedAtUtc = timestampUtc;
    }

    void IAuditableEntity.SetCreated(DateTime timestampUtc) => CreatedAtUtc = timestampUtc;

    void IAuditableEntity.SetModified(DateTime timestampUtc) => UpdatedAtUtc = timestampUtc;
}
