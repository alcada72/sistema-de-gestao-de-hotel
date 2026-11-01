# ⚡ alcadaCSS

## 📌 O que é

O **alcadaCSS** é um framework utilitário inspirado no Tailwind CSS, criado por **Alçada Kilundica**, com o objetivo de acelerar o desenvolvimento web através de classes simples, reutilizáveis e baseadas em variáveis CSS.

Ele permite criar interfaces rapidamente usando:

- Gradientes 🎨
- Tipografia 📝
- Layouts 📐
- Cards e seções 🧱
- Componentes reutilizáveis ⚡

---

# 🚀 Como funciona

O sistema usa variáveis CSS (`:root`) e classes utilitárias como:

```html
bg-gradient-to-r from-purple-500 via-pink-500 to-red-500
```

---

# 🎨 Gradientes

## 📌 Estrutura

```txt
bg-gradient-to-{direction}
from-{color}-{shade}
via-{color}-{shade}
to-{color}-{shade}
```

---

## 🌈 Direções disponíveis

- `bg-gradient-to-r` → direita
- `bg-gradient-to-l` → esquerda
- `bg-gradient-to-t` → cima
- `bg-gradient-to-b` → baixo

---

# 🧪 Exemplos práticos com HTML semântico

---

## 🏠 1. Página simples (estrutura real)

```html
<header class="bg-gradient-to-r from-purple-500 to-pink-500 text-white p-6">
  <h1>alcadaCSS</h1>
  <nav>
    <a href="#">Home</a>
    <a href="#">Docs</a>
    <a href="#">Sobre</a>
  </nav>
</header>

<main class="p-10">
  <section>
    <h2>Bem-vindo ao alcadaCSS</h2>
    <p>Um framework leve para acelerar o desenvolvimento web.</p>
  </section>
</main>

<footer class="bg-gray-900 text-white p-6">
  <p>© 2026 alcadaCSS - Todos os direitos reservados</p>
</footer>
```

---

## 🧱 2. Card de produto (UI real)

```html
<section class="p-10">
  <article
    class="bg-gradient-to-b from-blue-500 to-indigo-900 text-white p-6 rounded-xl"
  >
    <h2>Produto Premium</h2>
    <p>Este é um card de exemplo usando alcadaCSS.</p>

    <button class="bg-white text-black px-4 py-2 mt-4 rounded">
      Comprar agora
    </button>
  </article>
</section>
```

---

## 📰 3. Layout de artigo

```html
<article class="p-10">
  <h1>Título do Artigo</h1>

  <p>Este é um exemplo de texto usando o alcadaCSS para estruturar conteúdo.</p>

  <blockquote class="bg-gray-100 p-4 border-l-4 border-purple-500">
    "Frameworks utilitários ajudam a acelerar o desenvolvimento web."
  </blockquote>
</article>
```

---

## 🖼️ 4. Hero Section (landing page)

```html
<section
  class="bg-gradient-to-r from-purple-500 via-pink-500 to-red-500 text-white p-20 text-center"
>
  <h1>Constrói mais rápido com alcadaCSS</h1>
  <p>Um framework moderno, simples e poderoso</p>

  <button class="bg-white text-black px-6 py-2 mt-6 rounded">
    Começar agora
  </button>
</section>
```

---

## 📋 5. Formulário (UI real de login)

```html
<section class="p-10">
  <form class="bg-gray-100 p-6 rounded-xl max-w-md">
    <h2>Login</h2>

    <label>Email</label>
    <input type="email" class="p-2 border w-full mb-4" />

    <label>Password</label>
    <input type="password" class="p-2 border w-full mb-4" />

    <button class="bg-blue-500 text-white px-4 py-2 rounded w-full">
      Entrar
    </button>
  </form>
</section>
```

---

# ⚛️ React (exemplo real)

## 🧠 Layout completo

```jsx
export default function App() {
  return (
    <>
      <header className="bg-gradient-to-r from-purple-500 to-pink-500 text-white p-6">
        <h1>alcadaCSS</h1>
      </header>

      <main className="p-10">
        <section className="bg-gradient-to-b from-blue-500 to-indigo-900 text-white p-6 rounded-xl">
          <h2>Card React</h2>
          <p>Exemplo de componente com alcadaCSS</p>

          <button className="bg-white text-black px-4 py-2 mt-4 rounded">
            Ação
          </button>
        </section>
      </main>

      <footer className="bg-gray-900 text-white p-6">© 2026 alcadaCSS</footer>
    </>
  );
}
```

---

# 🎯 Benefícios

✔ Estrutura real de aplicações
✔ Componentes reutilizáveis
✔ Compatível com HTML semântico
✔ Funciona em React e outros frameworks
✔ Aprendizado fácil para iniciantes

---

# 👨‍💻 Criador

**Alçada Kilundica**
