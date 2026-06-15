using Atlas.Modules.Tenancy.Domain.Tenants.Events;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.SharedKernel.Abstractions;
using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Tenancy.Domain.Tenants;

public sealed class Tenant : AggregateRoot<long>, IAuditableEntity, ISoftDeletableEntity
{
    public const int CodeMaxLength = 50;
    public const int TimeZoneMaxLength = 100;

    private static readonly string[] SupportedLanguages = ["ar", "en"];

    private Tenant()
    {
        TenantCode = null!;
        Name = null!;
        SubscriptionStatus = null!;
        DefaultLanguage = null!;
        TimeZone = null!;
    }

    public string TenantCode { get; private set; }

    public LocalizedName Name { get; private set; }

    public SubscriptionStatus SubscriptionStatus { get; private set; }

    public int MaxCompanies { get; private set; }

    public int MaxUsers { get; private set; }

    public long BaseCurrencyId { get; private set; }

    public string DefaultLanguage { get; private set; }

    public string TimeZone { get; private set; }

    public bool IsActive { get; private set; }

    public bool IsDeleted { get; private set; }

    public DateTime? DeletedAtUtc { get; private set; }

    public long? DeletedBy { get; private set; }

    public DateTime CreatedAtUtc { get; private set; }

    public long CreatedBy { get; private set; }

    public DateTime? UpdatedAtUtc { get; private set; }

    public long? UpdatedBy { get; private set; }

    public static Tenant Create(
        string tenantCode,
        LocalizedName name,
        long baseCurrencyId,
        int maxCompanies,
        int maxUsers,
        string defaultLanguage,
        string timeZone,
        long createdBy)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(tenantCode);
        ArgumentNullException.ThrowIfNull(name);
        ArgumentException.ThrowIfNullOrWhiteSpace(timeZone);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(tenantCode.Trim().Length, CodeMaxLength);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(timeZone.Trim().Length, TimeZoneMaxLength);
        ArgumentOutOfRangeException.ThrowIfLessThan(maxCompanies, 1);
        ArgumentOutOfRangeException.ThrowIfLessThan(maxUsers, 1);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(baseCurrencyId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(createdBy);
        EnsureSupportedLanguage(defaultLanguage);

        var tenant = new Tenant
        {
            TenantCode = tenantCode.Trim(),
            Name = name,
            SubscriptionStatus = SubscriptionStatus.Trial,
            BaseCurrencyId = baseCurrencyId,
            MaxCompanies = maxCompanies,
            MaxUsers = maxUsers,
            DefaultLanguage = defaultLanguage,
            TimeZone = timeZone.Trim(),
            IsActive = true,
            IsDeleted = false,
            CreatedBy = createdBy
        };

        tenant.RaiseDomainEvent(new TenantCreatedDomainEvent(tenant.Id, tenant.TenantCode));
        return tenant;
    }

    public void UpdateProfile(
        LocalizedName name,
        int maxCompanies,
        int maxUsers,
        string defaultLanguage,
        string timeZone,
        long updatedBy)
    {
        ArgumentNullException.ThrowIfNull(name);
        ArgumentException.ThrowIfNullOrWhiteSpace(timeZone);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(timeZone.Trim().Length, TimeZoneMaxLength);
        ArgumentOutOfRangeException.ThrowIfLessThan(maxCompanies, 1);
        ArgumentOutOfRangeException.ThrowIfLessThan(maxUsers, 1);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);
        EnsureSupportedLanguage(defaultLanguage);

        Name = name;
        MaxCompanies = maxCompanies;
        MaxUsers = maxUsers;
        DefaultLanguage = defaultLanguage;
        TimeZone = timeZone.Trim();
        Touch(updatedBy);
    }

    public void ChangeSubscriptionStatus(SubscriptionStatus subscriptionStatus, long updatedBy)
    {
        ArgumentNullException.ThrowIfNull(subscriptionStatus);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);

        SubscriptionStatus = subscriptionStatus;
        Touch(updatedBy);
    }

    public void Activate(long updatedBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);
        IsActive = true;
        Touch(updatedBy);
    }

    public void Deactivate(long updatedBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);
        IsActive = false;
        Touch(updatedBy);
    }

    void IAuditableEntity.SetCreated(DateTime timestampUtc) => CreatedAtUtc = timestampUtc;

    void IAuditableEntity.SetModified(DateTime timestampUtc) => UpdatedAtUtc = timestampUtc;

    public void MarkDeleted(DateTime timestampUtc)
    {
        IsDeleted = true;
        DeletedAtUtc = timestampUtc;
    }

    private void Touch(long updatedBy) => UpdatedBy = updatedBy;

    private static void EnsureSupportedLanguage(string language)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(language);

        if (Array.IndexOf(SupportedLanguages, language) < 0)
        {
            throw new ArgumentException($"Unsupported language '{language}'. Allowed values: ar, en.", nameof(language));
        }
    }
}
