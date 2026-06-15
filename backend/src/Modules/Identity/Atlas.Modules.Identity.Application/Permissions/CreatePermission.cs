using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.Permissions;
using Atlas.Modules.Identity.Domain;
using Atlas.Modules.Identity.Domain.Permissions;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Identity.Application.Permissions;

public sealed record CreatePermissionCommand(
    string PermissionCode,
    string Module,
    string Action,
    string Scope,
    string? DescriptionAr,
    string? DescriptionEn,
    long PerformedBy) : ICommand<PermissionResponse>;

internal sealed class CreatePermissionCommandValidator : AbstractValidator<CreatePermissionCommand>
{
    public CreatePermissionCommandValidator()
    {
        RuleFor(command => command.PermissionCode).NotEmpty().MaximumLength(Permission.CodeMaxLength);
        RuleFor(command => command.Module).NotEmpty().MaximumLength(Permission.ModuleMaxLength);
        RuleFor(command => command.Action).NotEmpty();
        RuleFor(command => command.Scope).NotEmpty();
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class CreatePermissionCommandHandler(IPermissionRepository permissions, IUnitOfWork unitOfWork)
    : ICommandHandler<CreatePermissionCommand, PermissionResponse>
{
    public async Task<Result<PermissionResponse>> Handle(CreatePermissionCommand command, CancellationToken cancellationToken)
    {
        PermissionAction? action = PermissionAction.FromName(command.Action);
        if (action is null)
        {
            return Result.Failure<PermissionResponse>(IdentityErrors.PermissionActionInvalid(command.Action));
        }

        PermissionScope? scope = PermissionScope.FromName(command.Scope);
        if (scope is null)
        {
            return Result.Failure<PermissionResponse>(IdentityErrors.PermissionScopeInvalid(command.Scope));
        }

        if (await permissions.ExistsByCodeAsync(command.PermissionCode, cancellationToken))
        {
            return Result.Failure<PermissionResponse>(IdentityErrors.PermissionCodeAlreadyExists(command.PermissionCode));
        }

        Permission permission = Permission.Create(
            command.PermissionCode,
            command.Module,
            action,
            scope,
            command.DescriptionAr,
            command.DescriptionEn,
            command.PerformedBy);

        await permissions.AddAsync(permission, cancellationToken);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return IdentityMapper.ToResponse(permission);
    }
}
