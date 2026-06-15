using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.Modules.Tenancy.Domain.Tenants.Events;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using FluentAssertions;
using Xunit;

namespace Atlas.Modules.Tenancy.UnitTests.Domain;

public sealed class TenantTests
{
    private static LocalizedName Name() => LocalizedName.Create("شركة", "Company");

    [Fact]
    public void Create_WithValidData_StartsAsTrialAndActive()
    {
        Tenant tenant = Tenant.Create("ACME", Name(), baseCurrencyId: 1, maxCompanies: 5, maxUsers: 50, "ar", "Asia/Kuwait", createdBy: 7);

        tenant.TenantCode.Should().Be("ACME");
        tenant.SubscriptionStatus.Should().Be(SubscriptionStatus.Trial);
        tenant.IsActive.Should().BeTrue();
        tenant.IsDeleted.Should().BeFalse();
        tenant.CreatedBy.Should().Be(7);
    }

    [Fact]
    public void Create_RaisesTenantCreatedDomainEvent()
    {
        Tenant tenant = Tenant.Create("ACME", Name(), 1, 1, 10, "en", "Asia/Kuwait", 1);

        tenant.DomainEvents.Should().ContainSingle()
            .Which.Should().BeOfType<TenantCreatedDomainEvent>()
            .Which.TenantCode.Should().Be("ACME");
    }

    [Theory]
    [InlineData("fr")]
    [InlineData("")]
    public void Create_WithUnsupportedLanguage_Throws(string language)
    {
        Action act = () => Tenant.Create("ACME", Name(), 1, 1, 10, language, "Asia/Kuwait", 1);

        act.Should().Throw<ArgumentException>();
    }

    [Fact]
    public void Create_WithNonPositiveMaxCompanies_Throws()
    {
        Action act = () => Tenant.Create("ACME", Name(), 1, maxCompanies: 0, 10, "ar", "Asia/Kuwait", 1);

        act.Should().Throw<ArgumentOutOfRangeException>();
    }

    [Fact]
    public void ChangeSubscriptionStatus_UpdatesStatusAndAuditUser()
    {
        Tenant tenant = Tenant.Create("ACME", Name(), 1, 1, 10, "ar", "Asia/Kuwait", 1);

        tenant.ChangeSubscriptionStatus(SubscriptionStatus.Active, updatedBy: 99);

        tenant.SubscriptionStatus.Should().Be(SubscriptionStatus.Active);
        tenant.UpdatedBy.Should().Be(99);
    }
}
