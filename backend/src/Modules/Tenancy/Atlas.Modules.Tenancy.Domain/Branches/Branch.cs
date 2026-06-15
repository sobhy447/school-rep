using Atlas.Modules.Tenancy.Domain.Branches.Events;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Abstractions;
using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Tenancy.Domain.Branches;

public sealed class Branch : AggregateRoot<long>, IAuditableEntity, ISoftDeletableEntity, ITenantOwnedEntity, IVersionedEntity
{
    public const int CodeMaxLength = 50;
    public const int AddressMaxLength = 1000;
    public const int PhoneMaxLength = 50;

    private Branch()
    {
        BranchCode = null!;
        Name = null!;
    }

    public long TenantId { get; private set; }

    public long CompanyId { get; private set; }

    public string BranchCode { get; private set; }

    public LocalizedName Name { get; private set; }

    public string? Address { get; private set; }

    public string? Phone { get; private set; }

    public bool IsMainBranch { get; private set; }

    public bool IsActive { get; private set; }

    public int EntityVersion { get; private set; }

    public bool IsDeleted { get; private set; }

    public DateTime? DeletedAtUtc { get; private set; }

    public long? DeletedBy { get; private set; }

    public DateTime CreatedAtUtc { get; private set; }

    public long CreatedBy { get; private set; }

    public DateTime? UpdatedAtUtc { get; private set; }

    public long? UpdatedBy { get; private set; }

    public static Branch Create(
        long tenantId,
        long companyId,
        string branchCode,
        LocalizedName name,
        string? address,
        string? phone,
        bool isMainBranch,
        long createdBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(tenantId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(companyId);
        ArgumentException.ThrowIfNullOrWhiteSpace(branchCode);
        ArgumentNullException.ThrowIfNull(name);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(branchCode.Trim().Length, CodeMaxLength);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(createdBy);

        var branch = new Branch
        {
            TenantId = tenantId,
            CompanyId = companyId,
            BranchCode = branchCode.Trim(),
            Name = name,
            Address = Normalize(address, AddressMaxLength),
            Phone = Normalize(phone, PhoneMaxLength),
            IsMainBranch = isMainBranch,
            IsActive = true,
            IsDeleted = false,
            EntityVersion = 1,
            CreatedBy = createdBy
        };

        branch.RaiseDomainEvent(new BranchCreatedDomainEvent(branch.TenantId, branch.CompanyId, branch.BranchCode));
        return branch;
    }

    public void UpdateProfile(
        LocalizedName name,
        string? address,
        string? phone,
        bool isMainBranch,
        long updatedBy)
    {
        ArgumentNullException.ThrowIfNull(name);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);

        Name = name;
        Address = Normalize(address, AddressMaxLength);
        Phone = Normalize(phone, PhoneMaxLength);
        IsMainBranch = isMainBranch;
        UpdatedBy = updatedBy;
    }

    public void AdvanceVersion() => EntityVersion += 1;

    public void MarkDeleted(DateTime timestampUtc)
    {
        IsDeleted = true;
        DeletedAtUtc = timestampUtc;
    }

    void IAuditableEntity.SetCreated(DateTime timestampUtc) => CreatedAtUtc = timestampUtc;

    void IAuditableEntity.SetModified(DateTime timestampUtc) => UpdatedAtUtc = timestampUtc;

    private static string? Normalize(string? value, int maxLength)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return null;
        }

        string trimmed = value.Trim();
        ArgumentOutOfRangeException.ThrowIfGreaterThan(trimmed.Length, maxLength);
        return trimmed;
    }
}
