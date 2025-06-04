import React from "react";
const Home = () => {
  return (
    <div className="bg-white text-black font-sans">
      {/* Hero Banner */}
      <section
        className="w-full h-[450px] bg-cover bg-center flex items-center justify-center text-white relative"
        style={{
          backgroundImage:
            "url('https://images.unsplash.com/photo-1514516436985-e389f8de2fb3?fit=crop&w=1600&q=80')",
        }}
      >
        <div className="absolute inset-0 bg-black bg-opacity-60"></div>
        <div className="z-10 text-center px-6">
          <h1 className="text-4xl md:text-5xl font-bold mb-4">
            🔥 Kompa Fest 2025
          </h1>
          <p className="text-xl mb-6">
            May 25 • Bayfront Park, Miami FL
          </p>
          <a
            href="#"
            className="bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded text-lg"
          >
            Get Tickets
          </a>
        </div>
      </section>
      <section className="max-w-6xl mx-auto px-4 py-10">
        <h2 className="text-2xl font-bold mb-6">Upcoming Events</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
          <div className="bg-gray-100 p-4 rounded shadow text-center">
            <h3 className="font-bold text-lg">Afrobeats Rooftop</h3>
            <p className="text-sm text-gray-600">June 10 • Atlanta</p>
          </div>
          <div className="bg-gray-100 p-4 rounded shadow text-center">
            <h3 className="font-bold text-lg">Soca Sundaze</h3>
            <p className="text-sm text-gray-600">June 17 • Brooklyn</p>
          </div>
          <div className="bg-gray-100 p-4 rounded shadow text-center">
            <h3 className="font-bold text-lg">Jouvert Jam</h3>
            <p className="text-sm text-gray-600">July 1 • Fort Lauderdale</p>
          </div>
        </div>
      </section>
    </div>
  );
};
export default Home;
