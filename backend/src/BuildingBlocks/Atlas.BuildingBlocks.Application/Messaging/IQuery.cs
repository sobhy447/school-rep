using Atlas.SharedKernel.Results;
using MediatR;

namespace Atlas.BuildingBlocks.Application.Messaging;

public interface IQuery<TResponse> : IRequest<Result<TResponse>>
{
}
