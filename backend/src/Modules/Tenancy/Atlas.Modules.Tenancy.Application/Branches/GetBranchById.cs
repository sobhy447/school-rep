using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Branches;
using Atlas.Modules.Tenancy.Domain;
using Atlas.Modules.Tenancy.Domain.Branches;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Tenancy.Application.Branches;

public sealed record GetBranchByIdQuery(long BranchId) : IQuery<BranchResponse>;

public sealed class GetBranchByIdQueryHandler(IBranchRepository branches)
    : IQueryHandler<GetBranchByIdQuery, BranchResponse>
{
    public async Task<Result<BranchResponse>> Handle(GetBranchByIdQuery query, CancellationToken cancellationToken)
    {
        Branch? branch = await branches.GetByIdAsync(query.BranchId, cancellationToken);

        return branch is null
            ? Result.Failure<BranchResponse>(TenancyErrors.BranchNotFound(query.BranchId))
            : TenancyMapper.ToResponse(branch);
    }
}
