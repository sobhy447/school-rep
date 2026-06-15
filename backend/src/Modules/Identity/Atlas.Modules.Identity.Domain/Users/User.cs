using Atlas.Modules.Identity.Domain.Users.Events;
using Atlas.Modules.Identity.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Abstractions;
using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Identity.Domain.Users;

public sealed class User : AggregateRoot<long>, IAuditableEntity, ISoftDeletableEntity, ITenantOwnedEntity, IVersionedEntity
{
    public const int PhoneMaxLength = 50;
    public const int FullNameMaxLength = 500;
    public const int MfaSecretMaxLength = 500;
    public const int IpMaxLength = 50;

    private User()
    {
        Username = null!;
        PasswordHash = null!;
    }

    public long TenantId { get; private set; }

    public Username Username { get; private set; }

    public Email? Email { get; private set; }

    public PasswordHash PasswordHash { get; private set; }

    public string? Phone { get; private set; }

    public string? FullNameAr { get; private set; }

    public string? FullNameEn { get; private set; }

    public long? EmployeeId { get; private set; }

    public bool MfaEnabled { get; private set; }

    public string? MfaSecret { get; private set; }

    public int FailedLoginAttempts { get; private set; }

    public DateTime? LockoutUntilUtc { get; private set; }

    public DateTime? LastLoginAtUtc { get; private set; }

    public string? LastLoginIp { get; private set; }

    public DateTime? PasswordChangedAtUtc { get; private set; }

    public bool ForcePasswordChange { get; private set; }

    public int EntityVersion { get; private set; }

    public bool IsActive { get; private set; }

    public bool IsDeleted { get; private set; }

    public DateTime? DeletedAtUtc { get; private set; }

    public long? DeletedBy { get; private set; }

    public DateTime CreatedAtUtc { get; private set; }

    public long CreatedBy { get; private set; }

    public DateTime? UpdatedAtUtc { get; private set; }

    public long? UpdatedBy { get; private set; }

    public static User Create(
        long tenantId,
        Username username,
        PasswordHash passwordHash,
        Email? email,
        string? phone,
        string? fullNameAr,
        string? fullNameEn,
        long? employeeId,
        bool forcePasswordChange,
        long createdBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(tenantId);
        ArgumentNullException.ThrowIfNull(username);
        ArgumentNullException.ThrowIfNull(passwordHash);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(createdBy);

        if (employeeId.HasValue && employeeId.Value <= 0)
        {
            throw new ArgumentOutOfRangeException(nameof(employeeId), employeeId, "EmployeeId must be positive when provided.");
        }

        var user = new User
        {
            TenantId = tenantId,
            Username = username,
            PasswordHash = passwordHash,
            Email = email,
            Phone = Normalize(phone, PhoneMaxLength),
            FullNameAr = Normalize(fullNameAr, FullNameMaxLength),
            FullNameEn = Normalize(fullNameEn, FullNameMaxLength),
            EmployeeId = employeeId,
            MfaEnabled = false,
            FailedLoginAttempts = 0,
            ForcePasswordChange = forcePasswordChange,
            EntityVersion = 1,
            IsActive = true,
            IsDeleted = false,
            CreatedBy = createdBy
        };

        user.RaiseDomainEvent(new UserCreatedDomainEvent(user.TenantId, user.Username.Value));
        return user;
    }

    public void UpdateProfile(
        Email? email,
        string? phone,
        string? fullNameAr,
        string? fullNameEn,
        long updatedBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);

        Email = email;
        Phone = Normalize(phone, PhoneMaxLength);
        FullNameAr = Normalize(fullNameAr, FullNameMaxLength);
        FullNameEn = Normalize(fullNameEn, FullNameMaxLength);
        UpdatedBy = updatedBy;
    }

    public void ChangePassword(PasswordHash passwordHash, DateTime changedAtUtc, bool forcePasswordChange, long updatedBy)
    {
        ArgumentNullException.ThrowIfNull(passwordHash);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);

        PasswordHash = passwordHash;
        PasswordChangedAtUtc = changedAtUtc;
        ForcePasswordChange = forcePasswordChange;
        FailedLoginAttempts = 0;
        LockoutUntilUtc = null;
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
