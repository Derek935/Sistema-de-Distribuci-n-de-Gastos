function login() {
  let user = document.getElementById("usuario").value;
  let pass = document.getElementById("password").value;

  if (user === "admin" && pass === "1234") {
    alert("Bienvenido al sistema");
  } else {
    alert("Usuario o contraseña incorrectos");
  }
}
