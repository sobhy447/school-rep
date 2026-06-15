using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Branches;
using Atlas.Modules.Tenancy.Domain;
using Atlas.Modules.Tenancy.Domain.Branches;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Tenancy.Application.Branches;

public sealed record UpdateBranchCommand(
    long BranchId,
    string NameAr,
    string NameEn,
    string? Address,
    string? Phone,
    bool IsMainBranch,
    long PerformedBy) : ICommand<BranchResponse>;

internal sealed class UpdateBranchCommandValidator : AbstractValidator<UpdateBranchCommand>
{
    public UpdateBranchCommandValidator()
    {
        RuleFor(command => command.BranchId).GreaterThan(0);
        RuleFor(command => command.NameAr).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.NameEn).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.Address).MaximumLength(Branch.AddressMaxLength);
        RuleFor(command => command.Phone).MaximumLength(Branch.PhoneMaxLength);
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class UpdateBranchCommandHandler(IBranchRepository branches, IUnitOfWork unitOfWork)
    : ICommandHandler<UpdateBranchCommand, BranchResponse>
{
    public async Task<Result<BranchResponse>> Handle(UpdateBranchCommand command, CancellationToken cancellationToken)
    {
        Branch? branch = await branches.GetByIdAsync(command.BranchId, cancellationToken);
        if (branch is null)
        {
            return Result.Failure<BranchResponse>(TenancyErrors.BranchNotFound(command.BranchId));
        }

        LocalizedName name = LocalizedName.Create(command.NameAr, command.NameEn);

        branch.UpdateProfile(name, command.Address, command.Phone, command.IsMainBranch, command.PerformedBy);

        branches.Update(branch);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return TenancyMapper.ToResponse(branch);
    }
}
