using Atlas.Modules.Tenancy.Application.Tenants;
using FluentAssertions;
using FluentValidation;
using FluentValidation.Results;
using Xunit;

namespace Atlas.Modules.Tenancy.UnitTests.Application;

public sealed class CreateTenantValidatorTests
{
    private readonly IValidator<CreateTenantCommand> _validator =
        (IValidator<CreateTenantCommand>)Activator.CreateInstance(
            typeof(CreateTenantCommand).Assembly.GetType(
                "Atlas.Modules.Tenancy.Application.Tenants.CreateTenantCommandValidator")!)!;

    private static CreateTenantCommand Valid() =>
        new("ACME", "شركة", "Company", 1, 1, 10, "ar", "Asia/Kuwait", 1);

    [Fact]
    public void Validate_WithValidCommand_Passes()
    {
        ValidationResult result = _validator.Validate(Valid());

        result.IsValid.Should().BeTrue();
    }

    [Fact]
    public void Validate_WithUnsupportedLanguage_Fails()
    {
        ValidationResult result = _validator.Validate(Valid() with { DefaultLanguage = "fr" });

        result.IsValid.Should().BeFalse();
        result.Errors.Should().Contain(error => error.PropertyName == nameof(CreateTenantCommand.DefaultLanguage));
    }

    [Fact]
    public void Validate_WithNonPositivePerformedBy_Fails()
    {
        ValidationResult result = _validator.Validate(Valid() with { PerformedBy = 0 });

        result.IsValid.Should().BeFalse();
    }
}
