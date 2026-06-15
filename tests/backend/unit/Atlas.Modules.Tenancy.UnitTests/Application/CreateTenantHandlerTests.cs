using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Tenancy.Application.Tenants;
using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.SharedKernel.Results;
using FluentAssertions;
using NSubstitute;
using Xunit;

namespace Atlas.Modules.Tenancy.UnitTests.Application;

public sealed class CreateTenantHandlerTests
{
    private readonly ITenantRepository _tenants = Substitute.For<ITenantRepository>();
    private readonly IUnitOfWork _unitOfWork = Substitute.For<IUnitOfWork>();

    private static CreateTenantCommand Command() =>
        new("ACME", "شركة", "Company", BaseCurrencyId: 1, MaxCompanies: 3, MaxUsers: 25, "ar", "Asia/Kuwait", PerformedBy: 9);

    [Fact]
    public async Task Handle_WhenCodeExists_ReturnsConflict()
    {
        _tenants.ExistsByCodeAsync("ACME", Arg.Any<CancellationToken>()).Returns(true);
        var handler = new CreateTenantCommandHandler(_tenants, _unitOfWork);

        Result<Contracts.Tenants.TenantResponse> result = await handler.Handle(Command(), CancellationToken.None);

        result.IsFailure.Should().BeTrue();
        result.Error.Type.Should().Be(ErrorType.Conflict);
        await _tenants.DidNotReceive().AddAsync(Arg.Any<Tenant>(), Arg.Any<CancellationToken>());
        await _unitOfWork.DidNotReceive().SaveChangesAsync(Arg.Any<CancellationToken>());
    }

    [Fact]
    public async Task Handle_WhenCodeIsNew_PersistsAndReturnsResponse()
    {
        _tenants.ExistsByCodeAsync("ACME", Arg.Any<CancellationToken>()).Returns(false);
        var handler = new CreateTenantCommandHandler(_tenants, _unitOfWork);

        Result<Contracts.Tenants.TenantResponse> result = await handler.Handle(Command(), CancellationToken.None);

        result.IsSuccess.Should().BeTrue();
        result.Value.TenantCode.Should().Be("ACME");
        result.Value.SubscriptionStatus.Should().Be("TRIAL");
        await _tenants.Received(1).AddAsync(Arg.Any<Tenant>(), Arg.Any<CancellationToken>());
        await _unitOfWork.Received(1).SaveChangesAsync(Arg.Any<CancellationToken>());
    }
}
