using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Identity.Application.Abstractions;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.Users;
using Atlas.Modules.Identity.Domain;
using Atlas.Modules.Identity.Domain.Users;
using Atlas.Modules.Identity.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Identity.Application.Users;

public sealed record CreateUserCommand(
    string Username,
    string? Email,
    string Password,
    string? Phone,
    string? FullNameAr,
    string? FullNameEn,
    long? EmployeeId,
    bool ForcePasswordChange,
    long PerformedBy) : ICommand<UserResponse>;

internal sealed class CreateUserCommandValidator : AbstractValidator<CreateUserCommand>
{
    public CreateUserCommandValidator()
    {
        RuleFor(command => command.Username).NotEmpty().MaximumLength(Username.MaxLength);
        RuleFor(command => command.Email).MaximumLength(Email.MaxLength).EmailAddress()
            .When(command => !string.IsNullOrWhiteSpace(command.Email));
        RuleFor(command => command.Password).NotEmpty().MinimumLength(8);
        RuleFor(command => command.Phone).MaximumLength(User.PhoneMaxLength);
        RuleFor(command => command.FullNameAr).MaximumLength(User.FullNameMaxLength);
        RuleFor(command => command.FullNameEn).MaximumLength(User.FullNameMaxLength);
        RuleFor(command => command.EmployeeId).GreaterThan(0).When(command => command.EmployeeId is not null);
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class CreateUserCommandHandler(
    ITenantContext tenantContext,
    IUserRepository users,
    IPasswordHasher passwordHasher,
    IUnitOfWork unitOfWork) : ICommandHandler<CreateUserCommand, UserResponse>
{
    public async Task<Result<UserResponse>> Handle(CreateUserCommand command, CancellationToken cancellationToken)
    {
        long tenantId = tenantContext.TenantId;

        Username username = Username.Create(command.Username);
        if (await users.ExistsByUsernameAsync(tenantId, username.Value, cancellationToken))
        {
            return Result.Failure<UserResponse>(IdentityErrors.UsernameAlreadyExists(username.Value));
        }

        Email? email = null;
        if (!string.IsNullOrWhiteSpace(command.Email))
        {
            email = Email.Create(command.Email);
            if (await users.ExistsByEmailAsync(tenantId, email.Value, cancellationToken))
            {
                return Result.Failure<UserResponse>(IdentityErrors.EmailAlreadyExists(email.Value));
            }
        }

        PasswordHash passwordHash = PasswordHash.Create(passwordHasher.Hash(command.Password));

        User user = User.Create(
            tenantId,
            username,
            passwordHash,
            email,
            command.Phone,
            command.FullNameAr,
            command.FullNameEn,
            command.EmployeeId,
            command.ForcePasswordChange,
            command.PerformedBy);

        await users.AddAsync(user, cancellationToken);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return IdentityMapper.ToResponse(user);
    }
}
