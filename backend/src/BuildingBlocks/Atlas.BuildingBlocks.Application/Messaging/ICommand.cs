using Atlas.SharedKernel.Results;
using MediatR;

namespace Atlas.BuildingBlocks.Application.Messaging;

public interface IBaseCommand
{
}

public interface ICommand : IRequest<Result>, IBaseCommand
{
}

public interface ICommand<TResponse> : IRequest<Result<TResponse>>, IBaseCommand
{
}
