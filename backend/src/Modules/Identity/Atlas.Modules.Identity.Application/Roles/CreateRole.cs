using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.Roles;
using Atlas.Modules.Identity.Domain;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.Modules.Identity.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Identity.Application.Roles;

public sealed record CreateRoleCommand(
    string RoleCode,
    string NameAr,
    string NameEn,
    long PerformedBy) : ICommand<RoleResponse>;

internal sealed class CreateRoleCommandValidator : AbstractValidator<CreateRoleCommand>
{
    public CreateRoleCommandValidator()
    {
        RuleFor(command => command.RoleCode).NotEmpty().MaximumLength(Role.CodeMaxLength);
        RuleFor(command => command.NameAr).NotEmpty().MaximumLength(LocalizedText.MaxLength);
        RuleFor(command => command.NameEn).NotEmpty().MaximumLength(LocalizedText.MaxLength);
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class CreateRoleCommandHandler(
    ITenantContext tenantContext,
    IRoleRepository roles,
    IUnitOfWork unitOfWork) : ICommandHandler<CreateRoleCommand, RoleResponse>
{
    public async Task<Result<RoleResponse>> Handle(CreateRoleCommand command, CancellationToken cancellationToken)
    {
        long tenantId = tenantContext.TenantId;

        if (await roles.ExistsByCodeAsync(tenantId, command.RoleCode, cancellationToken))
        {
            return Result.Failure<RoleResponse>(IdentityErrors.RoleCodeAlreadyExists(command.RoleCode));
        }

        LocalizedText name = LocalizedText.Create(command.NameAr, command.NameEn);

        Role role = Role.Create(tenantId, command.RoleCode, name, isSystemRole: false, command.PerformedBy);

        await roles.AddAsync(role, cancellationToken);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return IdentityMapper.ToResponse(role);
    }
}
