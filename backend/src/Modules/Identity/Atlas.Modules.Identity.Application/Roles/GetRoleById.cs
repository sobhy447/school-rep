using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.Roles;
using Atlas.Modules.Identity.Domain;
using Atlas.Modules.Identity.Domain.Roles;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Identity.Application.Roles;

public sealed record GetRoleByIdQuery(long RoleId) : IQuery<RoleResponse>;

public sealed class GetRoleByIdQueryHandler(IRoleRepository roles)
    : IQueryHandler<GetRoleByIdQuery, RoleResponse>
{
    public async Task<Result<RoleResponse>> Handle(GetRoleByIdQuery query, CancellationToken cancellationToken)
    {
        Role? role = await roles.GetByIdAsync(query.RoleId, cancellationToken);

        return role is null
            ? Result.Failure<RoleResponse>(IdentityErrors.RoleNotFound(query.RoleId))
            : IdentityMapper.ToResponse(role);
    }
}
