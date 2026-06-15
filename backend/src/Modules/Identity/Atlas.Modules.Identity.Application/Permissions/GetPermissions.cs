using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.Permissions;
using Atlas.Modules.Identity.Domain.Permissions;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Identity.Application.Permissions;

public sealed record GetPermissionsQuery : IQuery<IReadOnlyList<PermissionResponse>>;

public sealed class GetPermissionsQueryHandler(IPermissionRepository permissions)
    : IQueryHandler<GetPermissionsQuery, IReadOnlyList<PermissionResponse>>
{
    public async Task<Result<IReadOnlyList<PermissionResponse>>> Handle(
        GetPermissionsQuery query,
        CancellationToken cancellationToken)
    {
        IReadOnlyList<Permission> result = await permissions.ListAllAsync(cancellationToken);

        IReadOnlyList<PermissionResponse> response = result.Select(IdentityMapper.ToResponse).ToList();
        return Result.Success(response);
    }
}
