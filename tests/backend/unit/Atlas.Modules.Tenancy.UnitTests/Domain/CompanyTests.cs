using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using FluentAssertions;
using Xunit;

namespace Atlas.Modules.Tenancy.UnitTests.Domain;

public sealed class CompanyTests
{
    private static Company NewCompany() =>
        Company.Create(
            "C-001",
            LocalizedName.Create("شركة", "Company"),
            baseCurrencyId: 1,
            Address.Create("Line 1", null, "Kuwait City", "kw"),
            fiscalYearStartMonth: 1,
            taxNumber: null,
            registrationNo: null,
            phone: null,
            email: null,
            logoUrl: null,
            createdBy: 3);

    [Fact]
    public void Create_InitializesVersionAndActiveState()
    {
        Company company = NewCompany();

        company.EntityVersion.Should().Be(1);
        company.IsActive.Should().BeTrue();
        company.Address.CountryCode.Should().Be("KW");
    }

    [Fact]
    public void SetTenantThenRegisterEvent_RaisesCompanyCreatedWithTenant()
    {
        Company company = NewCompany();

        company.SetTenant(42);
        company.RegisterCreatedEvent();

        company.TenantId.Should().Be(42);
        company.DomainEvents.Should().ContainSingle();
    }

    [Fact]
    public void AdvanceVersion_IncrementsEntityVersion()
    {
        Company company = NewCompany();

        company.AdvanceVersion();

        company.EntityVersion.Should().Be(2);
    }

    [Fact]
    public void Create_WithInvalidFiscalMonth_Throws()
    {
        Action act = () => Company.Create(
            "C-001",
            LocalizedName.Create("شركة", "Company"),
            1,
            Address.Create(null, null, null, "KW"),
            fiscalYearStartMonth: 13,
            null, null, null, null, null,
            createdBy: 1);

        act.Should().Throw<ArgumentOutOfRangeException>();
    }
}
