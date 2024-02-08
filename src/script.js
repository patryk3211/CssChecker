async function check_css() {
  var responsebox = document.querySelector("#responsebox > ul");
  // Removes all children
  responsebox.replaceChildren();
  var infoNode = document.createElement('li');
  infoNode.innerText = 'Oczekiwanie na odpowiedź serwera';
  responsebox.appendChild(infoNode);

  var form = document.getElementById('demoform');
  var template = form.template.value;
  var input = form.code.value;

  var response = await fetch(location + '/check_json.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'text/json'
    },
    body: JSON.stringify({ template: template, input: input })
  });

  var messages = await response.json();
  responsebox.replaceChildren();
  messages.forEach(msg => {
    var node = document.createElement('li');
    node.innerText = msg;
    responsebox.appendChild(node);
  });
}
