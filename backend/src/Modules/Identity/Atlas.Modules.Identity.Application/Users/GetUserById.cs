using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Identity.Application.Common;
using Atlas.Modules.Identity.Contracts.Users;
using Atlas.Modules.Identity.Domain;
using Atlas.Modules.Identity.Domain.Users;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Identity.Application.Users;

public sealed record GetUserByIdQuery(long UserId) : IQuery<UserResponse>;

public sealed class GetUserByIdQueryHandler(IUserRepository users)
    : IQueryHandler<GetUserByIdQuery, UserResponse>
{
    public async Task<Result<UserResponse>> Handle(GetUserByIdQuery query, CancellationToken cancellationToken)
    {
        User? user = await users.GetByIdAsync(query.UserId, cancellationToken);

        return user is null
            ? Result.Failure<UserResponse>(IdentityErrors.UserNotFound(query.UserId))
            : IdentityMapper.ToResponse(user);
    }
}
