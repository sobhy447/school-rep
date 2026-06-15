using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Identity.Application.Abstractions;
using Atlas.Modules.Identity.Application.Users;
using Atlas.Modules.Identity.Domain.Users;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentAssertions;
using NSubstitute;
using Xunit;

namespace Atlas.Modules.Identity.UnitTests.Application;

public sealed class CreateUserHandlerTests
{
    private const long TenantId = 7;

    private readonly ITenantContext _tenantContext = Substitute.For<ITenantContext>();
    private readonly IUserRepository _users = Substitute.For<IUserRepository>();
    private readonly IPasswordHasher _passwordHasher = Substitute.For<IPasswordHasher>();
    private readonly IUnitOfWork _unitOfWork = Substitute.For<IUnitOfWork>();

    public CreateUserHandlerTests()
    {
        _tenantContext.TenantId.Returns(TenantId);
        _passwordHasher.Hash(Arg.Any<string>()).Returns("$2a$12$hashedhashedhashedhashed");
    }

    private CreateUserCommandHandler Handler() => new(_tenantContext, _users, _passwordHasher, _unitOfWork);

    private static CreateUserCommand Command() =>
        new("jdoe", "jdoe@example.com", "Sup3rSecret!", null, null, null, null, true, PerformedBy: 1);

    [Fact]
    public async Task Handle_WhenUsernameExists_ReturnsConflict()
    {
        _users.ExistsByUsernameAsync(TenantId, "jdoe", Arg.Any<CancellationToken>()).Returns(true);

        Result<Contracts.Users.UserResponse> result = await Handler().Handle(Command(), CancellationToken.None);

        result.IsFailure.Should().BeTrue();
        result.Error.Code.Should().Be("Identity.User.UsernameAlreadyExists");
    }

    [Fact]
    public async Task Handle_WhenEmailExists_ReturnsConflict()
    {
        _users.ExistsByUsernameAsync(TenantId, "jdoe", Arg.Any<CancellationToken>()).Returns(false);
        _users.ExistsByEmailAsync(TenantId, "jdoe@example.com", Arg.Any<CancellationToken>()).Returns(true);

        Result<Contracts.Users.UserResponse> result = await Handler().Handle(Command(), CancellationToken.None);

        result.IsFailure.Should().BeTrue();
        result.Error.Code.Should().Be("Identity.User.EmailAlreadyExists");
    }

    [Fact]
    public async Task Handle_WhenValid_HashesPasswordAndPersists()
    {
        _users.ExistsByUsernameAsync(TenantId, "jdoe", Arg.Any<CancellationToken>()).Returns(false);
        _users.ExistsByEmailAsync(TenantId, "jdoe@example.com", Arg.Any<CancellationToken>()).Returns(false);

        Result<Contracts.Users.UserResponse> result = await Handler().Handle(Command(), CancellationToken.None);

        result.IsSuccess.Should().BeTrue();
        result.Value.Username.Should().Be("jdoe");
        result.Value.TenantId.Should().Be(TenantId);
        _passwordHasher.Received(1).Hash("Sup3rSecret!");
        await _users.Received(1).AddAsync(Arg.Is<User>(u => u.TenantId == TenantId), Arg.Any<CancellationToken>());
        await _unitOfWork.Received(1).SaveChangesAsync(Arg.Any<CancellationToken>());
    }
}
