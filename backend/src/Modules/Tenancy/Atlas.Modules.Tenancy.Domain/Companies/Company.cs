using Atlas.Modules.Tenancy.Domain.Companies.Events;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Abstractions;
using Atlas.SharedKernel.Primitives;

namespace Atlas.Modules.Tenancy.Domain.Companies;

public sealed class Company : AggregateRoot<long>, IAuditableEntity, ISoftDeletableEntity, ITenantOwnedEntity, IVersionedEntity
{
    public const int CodeMaxLength = 50;
    public const int TaxNumberMaxLength = 100;
    public const int RegistrationNoMaxLength = 100;
    public const int PhoneMaxLength = 50;
    public const int EmailMaxLength = 300;
    public const int LogoUrlMaxLength = 1000;

    private Company()
    {
        CompanyCode = null!;
        Name = null!;
        Address = null!;
    }

    public long TenantId { get; private set; }

    public string CompanyCode { get; private set; }

    public LocalizedName Name { get; private set; }

    public string? TaxNumber { get; private set; }

    public string? RegistrationNo { get; private set; }

    public long BaseCurrencyId { get; private set; }

    public Address Address { get; private set; }

    public string? Phone { get; private set; }

    public string? Email { get; private set; }

    public string? LogoUrl { get; private set; }

    public short FiscalYearStartMonth { get; private set; }

    public bool IsActive { get; private set; }

    public int EntityVersion { get; private set; }

    public bool IsDeleted { get; private set; }

    public DateTime? DeletedAtUtc { get; private set; }

    public long? DeletedBy { get; private set; }

    public DateTime CreatedAtUtc { get; private set; }

    public long CreatedBy { get; private set; }

    public DateTime? UpdatedAtUtc { get; private set; }

    public long? UpdatedBy { get; private set; }

    public static Company Create(
        long tenantId,
        string companyCode,
        LocalizedName name,
        long baseCurrencyId,
        Address address,
        short fiscalYearStartMonth,
        string? taxNumber,
        string? registrationNo,
        string? phone,
        string? email,
        string? logoUrl,
        long createdBy)
    {
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(tenantId);
        ArgumentException.ThrowIfNullOrWhiteSpace(companyCode);
        ArgumentNullException.ThrowIfNull(name);
        ArgumentNullException.ThrowIfNull(address);
        ArgumentOutOfRangeException.ThrowIfGreaterThan(companyCode.Trim().Length, CodeMaxLength);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(baseCurrencyId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(createdBy);
        EnsureFiscalMonth(fiscalYearStartMonth);

        var company = new Company
        {
            TenantId = tenantId,
            CompanyCode = companyCode.Trim(),
            Name = name,
            BaseCurrencyId = baseCurrencyId,
            Address = address,
            FiscalYearStartMonth = fiscalYearStartMonth,
            TaxNumber = Normalize(taxNumber, TaxNumberMaxLength),
            RegistrationNo = Normalize(registrationNo, RegistrationNoMaxLength),
            Phone = Normalize(phone, PhoneMaxLength),
            Email = Normalize(email, EmailMaxLength),
            LogoUrl = Normalize(logoUrl, LogoUrlMaxLength),
            IsActive = true,
            IsDeleted = false,
            EntityVersion = 1,
            CreatedBy = createdBy
        };

        company.RaiseDomainEvent(new CompanyCreatedDomainEvent(company.TenantId, company.CompanyCode));
        return company;
    }

    public void UpdateProfile(
        LocalizedName name,
        long baseCurrencyId,
        Address address,
        short fiscalYearStartMonth,
        string? taxNumber,
        string? registrationNo,
        string? phone,
        string? email,
        string? logoUrl,
        long updatedBy)
    {
        ArgumentNullException.ThrowIfNull(name);
        ArgumentNullException.ThrowIfNull(address);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(baseCurrencyId);
        ArgumentOutOfRangeException.ThrowIfNegativeOrZero(updatedBy);
        EnsureFiscalMonth(fiscalYearStartMonth);

        Name = name;
        BaseCurrencyId = baseCurrencyId;
        Address = address;
        FiscalYearStartMonth = fiscalYearStartMonth;
        TaxNumber = Normalize(taxNumber, TaxNumberMaxLength);
        RegistrationNo = Normalize(registrationNo, RegistrationNoMaxLength);
        Phone = Normalize(phone, PhoneMaxLength);
        Email = Normalize(email, EmailMaxLength);
        LogoUrl = Normalize(logoUrl, LogoUrlMaxLength);
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

    private static void EnsureFiscalMonth(short fiscalYearStartMonth)
    {
        if (fiscalYearStartMonth is < 1 or > 12)
        {
            throw new ArgumentOutOfRangeException(
                nameof(fiscalYearStartMonth),
                fiscalYearStartMonth,
                "Fiscal year start month must be between 1 and 12.");
        }
    }
}
