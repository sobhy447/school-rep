using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.Companies.Events;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using FluentAssertions;
using Xunit;

namespace Atlas.Modules.Tenancy.UnitTests.Domain;

public sealed class CompanyTests
{
    private const long TenantId = 42;

    private static Company NewCompany() =>
        Company.Create(
            TenantId,
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
    public void Create_AssignsTenantInitializesVersionAndActiveState()
    {
        Company company = NewCompany();

        company.TenantId.Should().Be(TenantId);
        company.EntityVersion.Should().Be(1);
        company.IsActive.Should().BeTrue();
        company.Address.CountryCode.Should().Be("KW");
    }

    [Fact]
    public void Create_RaisesCompanyCreatedDomainEventWithTenant()
    {
        Company company = NewCompany();

        company.DomainEvents.Should().ContainSingle()
            .Which.Should().BeOfType<CompanyCreatedDomainEvent>()
            .Which.TenantId.Should().Be(TenantId);
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
            TenantId,
            "C-001",
            LocalizedName.Create("شركة", "Company"),
            1,
            Address.Create(null, null, null, "KW"),
            fiscalYearStartMonth: 13,
            null, null, null, null, null,
            createdBy: 1);

        act.Should().Throw<ArgumentOutOfRangeException>();
    }

    [Fact]
    public void Create_WithNonPositiveTenant_Throws()
    {
        Action act = () => Company.Create(
            0,
            "C-001",
            LocalizedName.Create("شركة", "Company"),
            1,
            Address.Create(null, null, null, "KW"),
            fiscalYearStartMonth: 1,
            null, null, null, null, null,
            createdBy: 1);

        act.Should().Throw<ArgumentOutOfRangeException>();
    }
}
