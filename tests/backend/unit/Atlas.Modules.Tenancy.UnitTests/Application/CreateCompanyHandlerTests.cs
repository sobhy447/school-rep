using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Tenancy.Application.Companies;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentAssertions;
using NSubstitute;
using Xunit;

namespace Atlas.Modules.Tenancy.UnitTests.Application;

public sealed class CreateCompanyHandlerTests
{
    private const long TenantId = 42;

    private readonly ITenantContext _tenantContext = Substitute.For<ITenantContext>();
    private readonly ITenantRepository _tenants = Substitute.For<ITenantRepository>();
    private readonly ICompanyRepository _companies = Substitute.For<ICompanyRepository>();
    private readonly IUnitOfWork _unitOfWork = Substitute.For<IUnitOfWork>();

    public CreateCompanyHandlerTests() => _tenantContext.TenantId.Returns(TenantId);

    private CreateCompanyCommandHandler Handler() => new(_tenantContext, _tenants, _companies, _unitOfWork);

    private static CreateCompanyCommand Command() =>
        new("C-001", "شركة", "Company", BaseCurrencyId: 1, null, null, null, null, null, "KW", null, null, null, FiscalYearStartMonth: 1, PerformedBy: 5);

    private static Tenant TenantWithLimit(int maxCompanies) =>
        Tenant.Create("ACME", LocalizedName.Create("شركة", "Company"), 1, maxCompanies, 10, "ar", "Asia/Kuwait", 1);

    [Fact]
    public async Task Handle_WhenDuplicateCode_ReturnsConflict()
    {
        _tenants.GetByIdAsync(TenantId, Arg.Any<CancellationToken>()).Returns(TenantWithLimit(5));
        _companies.ExistsByCodeAsync(TenantId, "C-001", Arg.Any<CancellationToken>()).Returns(true);

        Result<Contracts.Companies.CompanyResponse> result = await Handler().Handle(Command(), CancellationToken.None);

        result.IsFailure.Should().BeTrue();
        result.Error.Type.Should().Be(ErrorType.Conflict);
    }

    [Fact]
    public async Task Handle_WhenCompanyLimitReached_ReturnsConflict()
    {
        _tenants.GetByIdAsync(TenantId, Arg.Any<CancellationToken>()).Returns(TenantWithLimit(1));
        _companies.ExistsByCodeAsync(TenantId, "C-001", Arg.Any<CancellationToken>()).Returns(false);
        _companies.CountByTenantAsync(TenantId, Arg.Any<CancellationToken>()).Returns(1);

        Result<Contracts.Companies.CompanyResponse> result = await Handler().Handle(Command(), CancellationToken.None);

        result.IsFailure.Should().BeTrue();
        result.Error.Code.Should().Be("Tenancy.Company.LimitReached");
    }

    [Fact]
    public async Task Handle_WhenValid_AssignsTenantAndPersists()
    {
        _tenants.GetByIdAsync(TenantId, Arg.Any<CancellationToken>()).Returns(TenantWithLimit(5));
        _companies.ExistsByCodeAsync(TenantId, "C-001", Arg.Any<CancellationToken>()).Returns(false);
        _companies.CountByTenantAsync(TenantId, Arg.Any<CancellationToken>()).Returns(0);

        Result<Contracts.Companies.CompanyResponse> result = await Handler().Handle(Command(), CancellationToken.None);

        result.IsSuccess.Should().BeTrue();
        result.Value.TenantId.Should().Be(TenantId);
        await _companies.Received(1).AddAsync(Arg.Is<Company>(c => c.TenantId == TenantId), Arg.Any<CancellationToken>());
        await _unitOfWork.Received(1).SaveChangesAsync(Arg.Any<CancellationToken>());
    }
}
