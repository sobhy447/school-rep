using Atlas.SharedKernel.Results;
using MediatR;

namespace Atlas.BuildingBlocks.Application.Messaging;

public interface IQueryHandler<in TQuery, TResponse> : IRequestHandler<TQuery, Result<TResponse>>
    where TQuery : IQuery<TResponse>
{
}
